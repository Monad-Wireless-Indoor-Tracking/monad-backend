<?php

namespace App\Service;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;

/**
 * Instrument-level telemetry for uploaded lab sessions.
 *
 * The auto-instrumentation already gives HTTP and Doctrine spans for free, which answers "is the
 * API healthy". This class answers the question the *experiment* has: is the fleet's phone
 * instrument producing usable measurements, and if not, which gate is failing.
 *
 * The metrics are chosen to mirror the EXP-P3 gates so a dashboard reads like the instrument's
 * own start-up sequence:
 *
 *   - `monad.lab.session.uploaded`     — artefacts arriving at all
 *   - `monad.lab.session.unpinned`     — sessions whose socket was NOT pinned to the experiment AP.
 *                                        This is the silent failure the whole design guards
 *                                        against: the app reports success, the observer node sees
 *                                        nothing. A non-zero rate here invalidates the runs.
 *   - `monad.lab.traffic.interval_cv`  — realised emission uniformity. The illuminator contract
 *                                        turns on this number: Doppler features need CV well under
 *                                        the broadcast baseline of 1.6–2.5.
 *   - `monad.lab.traffic.delivered`    — delivered / commanded rate.
 *   - `monad.lab.clock.offset_ms`      — |clock offset| against the collector; the sub-100 ms plane
 *                                        gate lives here.
 *
 * Everything is read from the session sidecar rather than measured server-side, because the
 * quantities are properties of the phone's radio link, not of this request.
 */
class LabTelemetry
{
    private CounterInterface $uploads;
    private CounterInterface $unpinned;
    private HistogramInterface $intervalCv;
    private HistogramInterface $delivered;
    private HistogramInterface $clockOffset;

    public function __construct()
    {
        $meter = Globals::meterProvider()->getMeter('monad.lab');

        $this->uploads = $meter->createCounter(
            'monad.lab.session.uploaded',
            'artefacts',
            'Lab-session artefacts accepted by the API',
        );
        $this->unpinned = $meter->createCounter(
            'monad.lab.session.unpinned',
            'sessions',
            'Sessions whose datagram socket was not pinned to the experiment AP',
        );
        $this->intervalCv = $meter->createHistogram(
            'monad.lab.traffic.interval_cv',
            '1',
            'Coefficient of variation of emitted inter-packet intervals',
        );
        $this->delivered = $meter->createHistogram(
            'monad.lab.traffic.delivered',
            '1',
            'Delivered rate as a fraction of the commanded rate',
        );
        $this->clockOffset = $meter->createHistogram(
            'monad.lab.clock.offset_ms',
            'ms',
            'Absolute clock offset against the collector',
        );
    }

    /**
     * Record one accepted artefact.
     *
     * @param array<string, mixed> $attributes
     */
    public function artefactAccepted(string $filename, array $attributes = []): void
    {
        $this->uploads->add(1, ['artefact' => $filename] + $attributes);
    }

    /**
     * Fold a completed session's sidecar into metrics.
     *
     * Called only for `metadata.json`, which the client uploads last precisely so that its arrival
     * marks the session complete — a partial upload therefore never produces a misleading metric.
     */
    public function sessionCompleted(string $rawSidecar): void
    {
        $sidecar = json_decode($rawSidecar, true);
        if (!is_array($sidecar)) {
            return;
        }

        $radio = $sidecar['radio'] ?? [];
        $summary = $sidecar['summary'] ?? [];
        $environment = $sidecar['environment'] ?? [];
        $identity = $sidecar['identity'] ?? [];

        $attributes = [
            'site' => (string) ($identity['site'] ?? ''),
            'platform' => (string) ($environment['platform'] ?? ''),
            'ap' => (string) ($radio['ap_id'] ?? ''),
        ];

        if (($radio['socket_pinned'] ?? false) !== true) {
            $this->unpinned->add(1, $attributes);
        }

        $commanded = (float) ($summary['commanded_rate_hz'] ?? 0.0);
        $achieved = (float) ($summary['achieved_rate_hz'] ?? 0.0);

        if ($commanded > 0.0) {
            $this->intervalCv->record((float) ($summary['interval_cv'] ?? 0.0), $attributes);
            $this->delivered->record($achieved / $commanded, $attributes);
        }

        // A session with no clock samples reports offset 0, which would be indistinguishable from
        // a perfectly disciplined one — record only when the discipline actually ran.
        if (($summary['clock_delay_ms'] ?? 0.0) > 0.0) {
            $this->clockOffset->record(abs((float) ($summary['clock_offset_ms'] ?? 0.0)), $attributes);
        }
    }
}
