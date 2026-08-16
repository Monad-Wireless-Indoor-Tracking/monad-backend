<?php

namespace App\Fleet;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The fleet's public vital signs, read from Mimir on behalf of the website.
 *
 * WHY THIS IS HERE AND NOT ON THE WEBSITE
 * ---------------------------------------
 * Mimir holds the fleet's entire operational history and has no authentication of
 * its own (`multitenancy_enabled: false`), so the docker bridge *is* its access
 * control: nothing publishes a host port for it and nothing should. This
 * container is already on that bridge, so it reaches `mimir:9009` by container
 * DNS at no cost.
 *
 * The public site is a host process (uvicorn under systemd). Letting it query
 * Mimir directly meant publishing a host-bound port for the metrics store, which
 * is exactly the wrong direction: the most exposed process on the box acquiring a
 * route into the observability stack. It reads this endpoint instead — one
 * backend it already depends on, over loopback, with the query vocabulary fixed
 * here.
 *
 * WHAT MAY BE READ
 * ----------------
 * A closed allow-list, below. Adding a metric is an edit here, in a reviewed
 * file, rather than a query string arriving from a caller — this is deliberately
 * NOT a PromQL proxy. A proxy with an allow-list would still let the shape of the
 * question travel over the wire; here the caller cannot ask anything at all, it
 * can only receive what this class decided to publish.
 *
 * Nothing naming a tailnet address, a path, a credential, a MAC or a participant
 * appears in any query, and only the `host` label survives into the response.
 *
 * FAILURE IS SOFT AND NAMED
 * -------------------------
 * An unreachable Mimir yields `reachable: false`, never zeros. A resting fleet
 * and an unreadable one are different facts and the website renders them as
 * different sentences; collapsing them here would destroy that distinction
 * before it ever reached a template.
 */
final class FleetMetricsReader
{
    /**
     * Per-node readings. Each query returns one series per node labelled `host`,
     * so one scrape fills the whole fleet and one node's absence cannot fail the
     * others.
     *
     * @var array<string, string>
     */
    private const NODE_QUERIES = [
        'soc_temp_c' => 'csid_node_temp_celsius{monad_node_role="csi-node"}',
        'thermal_headroom_c' => 'csid_node_thermal_headroom_celsius{monad_node_role="csi-node"}',
        'nic_temp_c' => 'monad_nic_temp_celsius{driver="iwlwifi"}',
        'cpu_busy_pct' => 'monad:host_cpu_busy:rate5m * 100',
        'memory_used_pct' => 'monad:host_memory_used_pct',
        'uptime_seconds' => 'time() - node_boot_time_seconds{monad_node_role="csi-node"}',
        'monitor_frames_per_s' => 'monad_nic:monitor_frames:rate5m',
    ];

    /**
     * Whether a capture process is up, and whether records are arriving. Two
     * separate facts and they must stay separate: `capture_active` counts csid's
     * heartbeat, `capture_rate_hz` counts records landing. On 2026-08-15 five
     * nodes reported the first and one the second, and a single field would have
     * claimed five capturing nodes.
     *
     * Deliberately unfiltered by `monad_node_role`: these are produced by the
     * Loki ruler from csid's log lines and carry only `{host, unit}`, so the
     * filter the queries above use would return an empty vector here.
     *
     * @var array<string, string>
     */
    private const CAPTURE_QUERIES = [
        'capture_active' => 'monad_csi:capture_active:2m',
        'capture_rate_hz' => 'monad_csi:capture_rate_hz:current',
    ];

    /**
     * Fleet-wide numbers. Every one returns a *single* series carrying no `host`
     * label, which is why they are read by a different reducer: a query that
     * returns several series is rejected rather than reduced, because a tile
     * silently printing the first of several would be a per-node number wearing
     * a fleet label.
     *
     * @var array<string, string>
     */
    private const SCALAR_QUERIES = [
        'nodes_reporting' => 'monad_fleet:node_reporting',
        'nodes_expected' => 'monad_fleet:node_expected_count',
        // `or vector(0)` because `count()` over an empty selector returns no
        // series at all, and an absent tile reads as "we do not know" when the
        // truth is a confident "none".
        'capture_processes' => 'count(monad_csi:capture_active:2m > 0) or vector(0)',
        'nodes_delivering' => 'count(monad_csi:capture_rate_hz:current > 0) or vector(0)',
        'csi_rate_hz' => 'sum(monad_csi:capture_rate_hz:current)',
        'frames_per_s' => 'sum(monad_nic:monitor_frames:rate5m)',
        'csi_records_session' => 'sum(monad_csi:capture_records:current)',
        'csi_bytes_session' => 'sum(monad_csi:capture_bytes:current)',
        // A min/max pair rather than one number: the honest answer has two
        // shapes, a fleet unanimously on one channel or a fleet mid-retune, and
        // a bare `min()` would print the first of several as if it were both.
        'monitor_channel_min' => 'min(monad_nic_channel{interface=~".+mon[0-9]+"})',
        'monitor_channel_max' => 'max(monad_nic_channel{interface=~".+mon[0-9]+"})',
        'hottest_node_c' => 'max(csid_node_temp_celsius{monad_node_role="csi-node"})',
        'nodes_throttled' => 'count(csid_node_throttled > 0) or vector(0)',
    ];

    /**
     * Matches the website's own snapshot TTL. The expected traffic is a lecture
     * hall of simultaneous QR scans behind one institutional NAT address, which a
     * per-IP limit would punish and a cache absorbs completely.
     */
    private const CACHE_TTL_SECONDS = 30;

    private const CACHE_KEY = 'fleet_public_snapshot';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        /** Empty means "no metrics store configured" — reported, never faked. */
        private readonly string $metricsUrl = '',
        private readonly float $timeoutSeconds = 2.5,
    ) {
    }

    /**
     * The whole fleet, cached, in the shape the website renders.
     *
     * @return array{reachable: bool, read_at: int, nodes: array<string, array<string, mixed>>, scalars: array<string, float>}
     */
    public function snapshot(): array
    {
        if ('' === $this->metricsUrl) {
            return $this->unreachable();
        }

        try {
            /** @var array{reachable: bool, read_at: int, nodes: array<string, array<string, mixed>>, scalars: array<string, float>} $snapshot */
            $snapshot = $this->cache->get(
                self::CACHE_KEY,
                function (ItemInterface $item): array {
                    $item->expiresAfter(self::CACHE_TTL_SECONDS);

                    return $this->fetch();
                }
            );

            return $snapshot;
        } catch (\Throwable $e) {
            $this->logger->info('[fleet] metrics read failed: {msg}', ['msg' => $e->getMessage()]);

            return $this->unreachable();
        }
    }

    /**
     * @return array{reachable: bool, read_at: int, nodes: array<string, array<string, mixed>>, scalars: array<string, float>}
     */
    private function fetch(): array
    {
        $values = [];
        $capture = [];
        $scalars = [];
        $failures = 0;

        foreach (self::NODE_QUERIES as $key => $query) {
            $series = $this->instant($query);
            if (null === $series) {
                ++$failures;
                continue;
            }
            foreach ($series as $host => $value) {
                $values[$host][$key] = $value;
            }
        }

        foreach (self::CAPTURE_QUERIES as $key => $query) {
            $series = $this->instant($query);
            if (null === $series) {
                ++$failures;
                continue;
            }
            foreach ($series as $host => $value) {
                $capture[$host][$key] = $value;
            }
        }

        foreach (self::SCALAR_QUERIES as $key => $query) {
            $value = $this->scalar($query);
            if (null === $value) {
                ++$failures;
                continue;
            }
            $scalars[$key] = $value;
        }

        if ([] === $values) {
            // Nothing came back for any node. That is not a resting fleet and
            // must not be published as one.
            $this->logger->info('[fleet] empty snapshot after {n} failed queries', ['n' => $failures]);

            return $this->unreachable();
        }

        $nodes = [];
        foreach ($values as $host => $readings) {
            $nodes[$host] = [
                'values' => $readings,
                // `null` where the node exports no such series at all, which is a
                // different fact from `false`/`0.0` and must survive the wire:
                // monad01 is the injector, reports temperature and frames, and has
                // no `monad_csi:*` series whatever. Coerced to a boolean it would
                // assert a resting capture process on a node that runs none.
                'capture_active' => isset($capture[$host]['capture_active'])
                    ? $capture[$host]['capture_active'] > 0.0
                    : null,
                'capture_rate_hz' => $capture[$host]['capture_rate_hz'] ?? null,
            ];
        }

        ksort($nodes);

        return [
            'reachable' => true,
            'read_at' => time(),
            'nodes' => $nodes,
            'scalars' => $scalars,
        ];
    }

    /**
     * One instant query to `{host: value}`. `null` marks a failed read.
     *
     * Keeping only `host` is the publication filter, not a convenience: it is
     * what stops `instance`, `job`, `cluster` and `monad_node_role` reaching a
     * caller. A result carrying no `host` is dropped outright — those belong in
     * SCALAR_QUERIES.
     *
     * @return array<string, float>|null
     */
    private function instant(string $query): ?array
    {
        $result = $this->query($query);
        if (null === $result) {
            return null;
        }

        $out = [];
        foreach ($result as $series) {
            $host = $series['metric']['host'] ?? null;
            $value = $series['value'][1] ?? null;
            if (is_string($host) && '' !== $host && null !== $value) {
                $out[$host] = (float) $value;
            }
        }

        return $out;
    }

    /**
     * One instant query to a single fleet-wide number. Anything returning more
     * than one series is rejected rather than reduced.
     */
    private function scalar(string $query): ?float
    {
        $result = $this->query($query);
        if (null === $result || 1 !== count($result)) {
            if (null !== $result) {
                $this->logger->warning('[fleet] scalar query returned {n} series: {q}', [
                    'n' => count($result),
                    'q' => $query,
                ]);
            }

            return null;
        }

        $value = reset($result)['value'][1] ?? null;

        return null === $value ? null : (float) $value;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function query(string $query): ?array
    {
        try {
            $response = $this->http->request('GET', rtrim($this->metricsUrl, '/').'/api/v1/query', [
                'query' => ['query' => $query],
                'timeout' => $this->timeoutSeconds,
            ]);

            $payload = $response->toArray(false);
        } catch (\Throwable $e) {
            $this->logger->debug('[fleet] query failed ({q}): {msg}', ['q' => $query, 'msg' => $e->getMessage()]);

            return null;
        }

        if (($payload['status'] ?? null) !== 'success') {
            return null;
        }

        $result = $payload['data']['result'] ?? null;

        return is_array($result) ? array_values($result) : null;
    }

    /**
     * @return array{reachable: bool, read_at: int, nodes: array<string, array<string, mixed>>, scalars: array<string, float>}
     */
    private function unreachable(): array
    {
        return ['reachable' => false, 'read_at' => time(), 'nodes' => [], 'scalars' => []];
    }
}
