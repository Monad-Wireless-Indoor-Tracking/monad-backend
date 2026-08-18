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

    case FINISH = 'finish';
}
