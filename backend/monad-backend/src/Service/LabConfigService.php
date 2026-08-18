<?php

namespace App\Service;

use App\Constants\ErrorCode;
use App\Exception\SystemException;

/**
 * Loads the lab bundle from a JSON file on disk.
 *
 * A file rather than a database table on purpose. The bundle describes physical reality — which
 * access point is up, where the anchors are surveyed, which collector is listening — and that is
 * edited by whoever is standing next to the hardware, often minutes before a session. A schema
 * would add a migration to every anchor added and would put the edit behind an admin UI that does
 * not exist.
 *
 * The file is validated on read rather than trusted: a malformed bundle must fail here, with a
 * clear error, instead of reaching a phone that will silently fail to find a collector.
 */
class LabConfigService
{
    public function __construct(
        private string $configPath,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function bundle(): array
    {
        if (!is_file($this->configPath)) {
            // An empty-but-valid bundle is better than a 500: the app falls back to its cached
            // copy or to an operator override typed into the lab console.
            return $this->emptyBundle();
        }

        $raw = file_get_contents($this->configPath);
        if ($raw === false) {
            throw new SystemException(ErrorCode::SYSTEM_INTERNAL_ERROR);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new SystemException(ErrorCode::SYSTEM_INTERNAL_ERROR);
        }

        return $decoded + $this->emptyBundle();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyBundle(): array
    {
        return [
            'version' => 0,
            'site' => '',
            'collector' => ['host' => '', 'udp_port' => 9999, 'http_base' => ''],
            'access_points' => [],
            'beacons' => ['uuid' => '', 'majors' => [], 'zones' => []],
            // The phone-side identity broadcast (ble_advertise steps / the broadcaster role).
            // namespace_uuid's last four bytes are replaced on the phone by the participant and
            // session keys, so the frame identifies a session, not a person. An empty namespace
            // means broadcasting is not configured on this deployment.
            'advertise' => [
                'namespace_uuid' => '',
                'interval_ms' => 250,
                'tx_power' => 'medium',
            ],
            'traffic_profiles' => [],
            'clock_sync' => [
                'burst_size' => 20,
                'burst_spacing_ms' => 50,
                'resync_seconds' => 600,
                'timeout_ms' => 1000,
            ],
        ];
    }
}
