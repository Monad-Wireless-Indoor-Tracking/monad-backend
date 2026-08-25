<?php

namespace App\Enum;

enum QuestStepType: string
{
    case START = 'start';
    case WAIT = 'wait';
    case SCAN_QR = 'scan_qr';
    case CONNECT_TO_AP = 'connect_to_ap';
    case WALK_TO = 'walk_to';
    case FIND_BLE_DEVICE = 'find_ble_device';
    /** Run an optional sensor module (room scan, UWB ranging); gated by device capability. */
    case SENSOR_CAPTURE = 'sensor_capture';
    /**
     * Broadcast the lab identity frame (a 128-bit service UUID derived from the bundle's advertise
     * namespace) for the step's duration, so the fleet's BLE scan can observe the participant.
     * Gated by the `ble.advertise` device capability — iOS can only honour it in the foreground.
     */
    case BLE_ADVERTISE = 'ble_advertise';

    /**
     * IP-140 — scan one of a named set of surveyed points, then hold still for a fixed dwell.
     *
     * The difference from `scan_qr` is that a probe knows *where* the code is. Its `targets` carry
     * a resolved label, room and kind, generated from the PostGIS placement layouts, so a matched
     * scan yields a position rather than only a step completion. The dwell is bracketed with
     * `dwell_start` / `dwell_end` session markers.
     *
     * It never starts or stops the identity broadcast. That is session-scoped and declared in the
     * start step's `features` block, so the frame stays on air across the walk between two probes —
     * which is the part of the record the fleet's per-node RSSI reconstructs a trajectory from.
     */
    case PROBE = 'probe';

    case FINISH = 'finish';
}
