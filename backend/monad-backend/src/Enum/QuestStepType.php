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

    case FINISH = 'finish';
}
