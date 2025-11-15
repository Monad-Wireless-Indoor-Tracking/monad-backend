# Quest Step Configuration Specification

This document defines the configuration structure for different types of quest steps in the Monad application.

## Step Types Overview

| Type | Description | Completion Requirement | Skippable |
|------|-------------|------------------------|-----------|
| `qr_code` | Scan a QR code at a location | QR code value matches | No |
| `find_ble_device` | Find and connect to BLE beacon | Device ID detected | No |
| `wait` | Wait for specified duration | Timer expires | No |
| `text_box` | Read instructions/information | User acknowledges | Yes |

---

## 1. QR Code Step

**Type:** `qr_code`

**Description:** User must scan a QR code at a specific location. The scanned value must match the expected value.

### Configuration Structure

```json
{
  "type": "qr_code", # enum
  "name": "Scan entrance QR code",
  "description": "Find and scan the QR code at the main entrance",
  "config": {
    "expected_value": "ENTRANCE_A_2024",
    "location": "Main entrance, next to the door handle",
  }
}
```

### Configuration Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | Yes | Must be `"qr_code"` |
| `name` | string | Yes | Step name/title shown to user |
| `description` | string | Yes | Brief description of what to do |
| `config.expected_value` | string | Yes | The exact QR code value to match |
| `config.location` | string | Yes | Human-readable location description |

### App Behavior

**Button Text:** `"Scan QR Code"`

**Completion Criteria:**
1. User taps "Scan QR Code" button
2. QR code scanner opens inside of the box 
3. QR code is detected and decoded
4. Decoded value matches `config.expected_value` exactly (case-insensitive)
5. Step marked as completed, disable the scanner , moved to next step

**Error Handling:**
- If scanned value doesn't match: This is not the expected QR code. Please Try again"

**Data Collected:**
- Timestamp of scan
- Scanned value (for verification)

---

## 2. Find BLE Device Step

**Type:** `find_ble_device`

**Description:** User must find and detect a specific Bluetooth Low Energy beacon/device.

### Configuration Structure

```json
{
  "type": "find_ble_device",
  "name": "Find BLE beacon at Lab A",
  "description": "Walk to Lab A and wait until the beacon is detected",
  "config": {
    "device_name": "Monad_Beacon_LabA",
    "device_id": "A4:C1:38:F2:1D:8E",
  }
}
```

### Configuration Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | Yes | Must be `"find_ble_device"` |
| `name` | string | Yes | Step name/title shown to user |
| `description` | string | Yes | Brief description of what to do |
| `config.device_name` | string | Yes | Human-readable BLE device name |
| `config.device_id` | string | Yes | BLE MAC address (format: `XX:XX:XX:XX:XX:XX`) |

### App Behavior

**Button Text:** `"Start Scanning"`

**Completion Criteria:**
1. User taps "Start Scanning" button
2. App starts BLE scan in background
3. Device with matching `device_id` is detected
4. RSSI is above `rssi_threshold` (if specified)
5. Device remains detected for `detection_duration` seconds
6. Step marked as completed

**UI During Scan:**
- Show "Searching..." with animated indicator
- Display RSSI strength meter when device is detected but below threshold
- Show countdown when device is detected and above threshold
- Button changes to "Cancel Scan" (allows user to stop)

**Auto-completion:**
- If device is detected immediately when step starts, auto-complete after `detection_duration`

**Data Collected:**
- Timestamp of detection
- RSSI values (multiple samples during detection period)
- Detection duration
- GPS coordinates (if available)
- All BLE advertisements received during scan

---

## 3. Wait Step

**Type:** `wait`

**Description:** User must wait for a specified duration. Used for time-based experiments or breaks.

### Configuration Structure

```json
{
  "type": "wait",
  "name": "Wait 30 seconds",
  "description": "Please remain stationary for 30 seconds while we collect data",
  "config": {
    "timeout_seconds": 30,
    "allow_background": false,
    "instructions": "Stand still in your current position. Do not move until the timer completes."
  }
}
```

### Configuration Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | Yes | Must be `"wait"` |
| `name` | string | Yes | Step name/title shown to user |
| `description` | string | Yes | Brief description of what to do during wait |
| `config.timeout_seconds` | integer | Yes | Duration to wait in seconds |
| `config.allow_background` | boolean | No | Allow app to go to background (default: false) |
| `config.instructions` | string | No | Additional instructions for the wait period |

### App Behavior

**Button Text:** `"Start Timer"` (before starting) � `"Waiting..."` (during countdown)

**Completion Criteria:**
1. User taps "Start Timer" button
2. Countdown timer starts from `timeout_seconds`
3. Timer counts down to 0
4. Step marked as completed

**UI During Wait:**
- Large countdown display (e.g., "0:30" � "0:00")
- Progress circle/bar showing time remaining
- If `allow_background: false`, show warning if user tries to leave app
- Optional: Pause button (timer can be paused and resumed)

**Auto-start Option:**
- Can be configured to start automatically when step is reached

**Data Collected:**
- Start timestamp
- End timestamp
- Whether timer was paused (and pause durations)
- App state changes (foreground/background)

---

## 4. Text Box Step

**Type:** `text_box`

**Description:** Display information or instructions to the user. No action required except acknowledgment. Can be skipped.

### Configuration Structure

```json
{
  "type": "text_box",
  "name": "Welcome to the experiment",
  "description": "You are about to participate in a BLE positioning experiment. During this quest, you will:\n\n1. Visit multiple locations\n2. Scan QR codes\n3. Stand near BLE beacons\n\nPlease keep your phone's Bluetooth enabled throughout the experiment.",
  "config": {
    "allow_skip": true,
    "button_text": "I Understand"
  }
}
```

### Configuration Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | Yes | Must be `"text_box"` |
| `name` | string | Yes | Step title/heading shown to user |
| `description` | string | Yes | Full text content to display (supports markdown) |
| `config.allow_skip` | boolean | No | Allow skipping without reading (default: true) |
| `config.button_text` | string | No | Custom button text (default: "Continue") |

### App Behavior

**Button Text:** `{config.button_text}` or `"Continue"` (default)

**Completion Criteria:**
1. User taps the button
2. Step marked as completed

**UI Display:**
- Show `name` as heading
- Show `description` in scrollable text area (supports markdown formatting)
- If text is long, require scroll to bottom before enabling button (optional)
- If `allow_skip: true`, show small "Skip" link

**Skipping:**
- If `allow_skip: true`, show secondary "Skip" button/link
- Skipped steps are still marked as completed but with `skipped: true` flag

**Data Collected:**
- Timestamp of acknowledgment
- Time spent on screen (reading duration)
- Whether step was skipped
- Scroll depth (if applicable)

---

## Complete Example: Quest Configuration

```json
{
  "quest_id": "ble_experiment_001",
  "name": "BLE Indoor Positioning Experiment",
  "description": "Collect BLE advertisement data at multiple locations",
  "steps": [
    {
      "step_id": 1,
      "type": "text_box",
      "name": "Welcome",
      "description": "Welcome to the experiment! You will visit 3 locations and collect BLE data.",
      "config": {
        "allow_skip": true,
        "button_text": "Let's Start"
      }
    },
    {
      "step_id": 2,
      "type": "qr_code",
      "name": "Scan Start Location",
      "description": "Scan the QR code at the starting position",
      "config": {
        "expected_value": "START_POS_A1",
        "location": "Room 101, center of the room",
        "instructions": "QR code is on the floor, marked with yellow tape"
      }
    },
    {
      "step_id": 3,
      "type": "find_ble_device",
      "name": "Detect Beacon A",
      "description": "Find BLE Beacon A",
      "config": {
        "device_name": "Monad_Beacon_A",
        "device_id": "A4:C1:38:F2:1D:8E",
        "rssi_threshold": -70,
        "detection_duration": 5,
        "instructions": "Stand at the marked position until beacon is detected"
      }
    },
    {
      "step_id": 4,
      "type": "wait",
      "name": "Data Collection",
      "description": "Please wait while we collect BLE data",
      "config": {
        "timeout_seconds": 30,
        "allow_background": false,
        "instructions": "Remain stationary. Keep phone in hand at chest height."
      }
    },
    {
      "step_id": 5,
      "type": "text_box",
      "name": "Moving to Next Location",
      "description": "Great! Now walk to Location B (Room 102).\n\nTake the hallway to your right.",
      "config": {
        "allow_skip": false,
        "button_text": "I'm Ready"
      }
    },
    {
      "step_id": 6,
      "type": "qr_code",
      "name": "Scan Location B",
      "description": "Scan the QR code at Location B",
      "config": {
        "expected_value": "LOCATION_B_102",
        "location": "Room 102, near the window",
        "instructions": "QR code is on the wall at eye level"
      }
    }
  ]
}
```

---

## Step Completion Summary

| Step Type | Button Text | Completion Trigger | Data Collected |
|-----------|-------------|-------------------|----------------|
| `qr_code` | "Scan QR Code" | QR value matches `expected_value` | Timestamp, scanned value, GPS |
| `find_ble_device` | "Start Scanning" | Device detected with sufficient RSSI for `detection_duration` | Timestamp, RSSI samples, BLE ads, GPS |
| `wait` | "Start Timer" | Timer reaches 0 | Start/end timestamps, pauses |
| `text_box` | Custom or "Continue" | User taps button | Timestamp, reading duration, skipped flag |

---

## Implementation Notes

### Backend Validation

When a step is submitted as completed:

1. **QR Code:** Verify `scanned_value` matches `config.expected_value`
2. **BLE Device:** Verify `device_id` is present in submitted BLE data
3. **Wait:** Verify time difference between start and completion is e `timeout_seconds`
4. **Text Box:** No validation needed (always accept)

### Mobile App State

Store in local database:
```json
{
  "quest_id": "ble_experiment_001",
  "current_step": 3,
  "step_states": [
    {
      "step_id": 1,
      "status": "completed",
      "completed_at": "2025-01-14T14:30:22Z",
      "skipped": true
    },
    {
      "step_id": 2,
      "status": "completed",
      "completed_at": "2025-01-14T14:32:15Z",
      "data": {
        "scanned_value": "START_POS_A1",
        "gps": {"lat": 48.1234, "lng": 17.5678}
      }
    },
    {
      "step_id": 3,
      "status": "in_progress",
      "started_at": "2025-01-14T14:35:00Z"
    }
  ]
}
```

### Error Messages

- **QR Code mismatch:** "Wrong QR code. Expected location: {config.location}"
- **BLE not found:** "Beacon not detected. Make sure you're at: {config.instructions}"
- **BLE weak signal:** "Signal too weak. Move closer to the beacon. Current: {rssi} dBm, Required: {threshold} dBm"
- **Wait interrupted:** "Timer was paused. Resume to continue."
- **App in background:** "Please keep the app open during data collection."

---

## Future Step Types (Ideas)

- `gps_location` - Arrive at GPS coordinates
- `photo_capture` - Take a photo of something
- `motion_activity` - Perform specific movement (walk, sit, stand)
- `form_input` - Collect user input (text, numbers, ratings)
- `audio_record` - Record audio notes
- `multi_choice` - Answer multiple choice question
