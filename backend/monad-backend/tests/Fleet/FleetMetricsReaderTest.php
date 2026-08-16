<?php

namespace App\Tests\Fleet;

use App\Fleet\FleetMetricsReader;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * What the website is allowed to be told about the fleet.
 *
 * These are honesty rules, not plumbing. Each one is a way the endpoint could
 * report something plausible and wrong, and each has been wrong in production at
 * least once somewhere in this stack:
 *
 *  - an unreachable store reported as a resting fleet (zeros instead of a state)
 *  - a node with no capture series reported as "not capturing" rather than
 *    "does not say" — monad01 is the injector and exports none
 *  - a fleet-wide tile silently printing the first of several series
 *  - a label other than `host` reaching the caller
 */
class FleetMetricsReaderTest extends TestCase
{
    /** @param array<string, string> $responses query-substring => JSON body */
    private function reader(array $responses, string $url = 'http://mimir:9009/prometheus'): FleetMetricsReader
    {
        $client = new MockHttpClient(function (string $method, string $requestUrl) use ($responses): MockResponse {
            foreach ($responses as $needle => $body) {
                if (str_contains(urldecode($requestUrl), $needle)) {
                    return new MockResponse($body, ['response_headers' => ['content-type' => 'application/json']]);
                }
            }

            return new MockResponse(
                json_encode(['status' => 'success', 'data' => ['resultType' => 'vector', 'result' => []]]),
                ['response_headers' => ['content-type' => 'application/json']]
            );
        });

        return new FleetMetricsReader($client, $this->cache(), new NullLogger(), $url);
    }

    private function cache(): CacheInterface
    {
        return new TagAwareAdapter(new ArrayAdapter());
    }

    private static function vector(array $series): string
    {
        return json_encode(['status' => 'success', 'data' => ['resultType' => 'vector', 'result' => $series]]);
    }

    private static function sample(array $metric, string $value): array
    {
        return ['metric' => $metric, 'value' => [1786820000, $value]];
    }

    // ---------------------------------------------------------------- reachability

    public function testAnUnconfiguredStoreIsReportedNotFaked(): void
    {
        $reader = new FleetMetricsReader(new MockHttpClient(), $this->cache(), new NullLogger(), '');

        $snapshot = $reader->snapshot();

        self::assertFalse($snapshot['reachable']);
        self::assertSame([], $snapshot['nodes']);
        self::assertSame([], $snapshot['scalars']);
    }

    public function testAnUnreachableStoreIsNotARestingFleet(): void
    {
        // Every query answers with an empty vector: nothing is readable.
        $snapshot = $this->reader([])->snapshot();

        self::assertFalse($snapshot['reachable'], 'no readings must not be published as a quiet fleet');
        self::assertSame([], $snapshot['nodes']);
    }

    // ---------------------------------------------------------------- per-node

    public function testANodeWithNoCaptureSeriesSaysNothingRatherThanNo(): void
    {
        // monad01 is the injector: it reports temperature and frames and exports
        // no `monad_csi:*` series at all. `false` would assert a stopped
        // recorder on a node that runs none.
        $snapshot = $this->reader([
            'csid_node_temp_celsius' => self::vector([
                self::sample(['host' => 'monad01'], '68.85'),
                self::sample(['host' => 'monad02'], '77.1'),
            ]),
            'monad_csi:capture_active:2m' => self::vector([
                self::sample(['host' => 'monad02'], '12'),
            ]),
            'monad_csi:capture_rate_hz:current' => self::vector([
                self::sample(['host' => 'monad02'], '0'),
            ]),
        ])->snapshot();

        self::assertTrue($snapshot['reachable']);
        self::assertNull($snapshot['nodes']['monad01']['capture_active']);
        self::assertNull($snapshot['nodes']['monad01']['capture_rate_hz']);
        self::assertTrue($snapshot['nodes']['monad02']['capture_active']);
    }

    public function testARunningProcessDeliveringNothingKeepsBothFacts(): void
    {
        // The pair that must never collapse into one field: on 2026-08-15 five
        // nodes had a capture process up and one was delivering records.
        $snapshot = $this->reader([
            'csid_node_temp_celsius' => self::vector([self::sample(['host' => 'monad02'], '77.1')]),
            'monad_csi:capture_active:2m' => self::vector([self::sample(['host' => 'monad02'], '12')]),
            'monad_csi:capture_rate_hz:current' => self::vector([self::sample(['host' => 'monad02'], '0')]),
        ])->snapshot();

        self::assertTrue($snapshot['nodes']['monad02']['capture_active']);
        self::assertSame(0.0, $snapshot['nodes']['monad02']['capture_rate_hz']);
    }

    public function testOnlyTheHostLabelSurvives(): void
    {
        $snapshot = $this->reader([
            'csid_node_temp_celsius' => self::vector([
                self::sample([
                    'host' => 'monad02',
                    'instance' => 'monad02',
                    'job' => 'integrations/unix',
                    'cluster' => 'monad',
                    'monad_node_role' => 'csi-node',
                ], '77.1'),
            ]),
        ])->snapshot();

        $encoded = json_encode($snapshot);
        self::assertStringContainsString('monad02', $encoded);
        foreach (['integrations/unix', 'cluster', 'csi-node', 'instance'] as $leak) {
            self::assertStringNotContainsString($leak, $encoded, "label leaked: {$leak}");
        }
    }

    public function testASeriesWithNoHostIsDroppedRatherThanKeyedOnNothing(): void
    {
        $snapshot = $this->reader([
            'csid_node_temp_celsius' => self::vector([
                self::sample(['host' => 'monad02'], '77.1'),
                self::sample([], '61.0'),
            ]),
        ])->snapshot();

        self::assertSame(['monad02'], array_keys($snapshot['nodes']));
    }

    // ---------------------------------------------------------------- fleet-wide

    public function testAScalarIsPublishedWhenExactlyOneSeriesComesBack(): void
    {
        $snapshot = $this->reader([
            'csid_node_temp_celsius' => self::vector([self::sample(['host' => 'monad02'], '77.1')]),
            'monad_fleet:node_reporting' => self::vector([self::sample([], '6')]),
        ])->snapshot();

        self::assertSame(6.0, $snapshot['scalars']['nodes_reporting']);
    }

    public function testAMultiSeriesScalarIsRejectedRatherThanReduced(): void
    {
        // A tile printing the first of several would be a per-node number
        // wearing a fleet label.
        $snapshot = $this->reader([
            'csid_node_temp_celsius' => self::vector([self::sample(['host' => 'monad02'], '77.1')]),
            'monad_fleet:node_reporting' => self::vector([
                self::sample(['host' => 'monad02'], '1'),
                self::sample(['host' => 'monad03'], '1'),
            ]),
        ])->snapshot();

        self::assertArrayNotHasKey('nodes_reporting', $snapshot['scalars']);
    }

    // ---------------------------------------------------------------- caching

    public function testTheStoreIsReadOncePerWindowHoweverManyScansArrive(): void
    {
        // The expected traffic is a lecture hall of simultaneous QR scans behind
        // one NAT address. Caching is the load-shedding strategy; a per-IP limit
        // would punish exactly that case.
        $calls = 0;
        $client = new MockHttpClient(function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse(
                self::vector([self::sample(['host' => 'monad02'], '77.1')]),
                ['response_headers' => ['content-type' => 'application/json']]
            );
        });
        $reader = new FleetMetricsReader($client, $this->cache(), new NullLogger(), 'http://mimir:9009/prometheus');

        $reader->snapshot();
        $first = $calls;
        for ($i = 0; $i < 20; ++$i) {
            $reader->snapshot();
        }

        self::assertSame($first, $calls, 'a cached window must cost no upstream reads');
    }
}
