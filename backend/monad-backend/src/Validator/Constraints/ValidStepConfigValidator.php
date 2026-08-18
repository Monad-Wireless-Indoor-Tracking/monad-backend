<?php

namespace App\Validator\Constraints;

use App\Enum\QuestStepType;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidStepConfigValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidStepConfig) {
            throw new UnexpectedTypeException($constraint, ValidStepConfig::class);
        }

        if ($value === null) {
            return;
        }

        if (!is_array($value)) {
            return;
        }

        // Get the parent object to access the type
        $object = $this->context->getObject();
        if (!$object || !property_exists($object, 'type')) {
            return;
        }

        $type = $object->type;
        if ($type === null) {
            return;
        }

        // Validate config based on step type
        $stepType = QuestStepType::tryFrom($type);
        if ($stepType === null) {
            $this->context->buildViolation($constraint->messageInvalidType)
                ->setParameter('{{ type }}', $type)
                ->addViolation();
            return;
        }

        $this->validateConfigForType($value, $stepType, $constraint);
    }

    private function validateConfigForType(array $config, QuestStepType $type, ValidStepConfig $constraint): void
    {
        match ($type) {
            QuestStepType::SCAN_QR => $this->validateScanQrConfig($config, $constraint),
            QuestStepType::FIND_BLE_DEVICE => $this->validateFindBleDeviceConfig($config, $constraint),
            QuestStepType::WAIT => $this->validateWaitConfig($config, $constraint),
            QuestStepType::START, QuestStepType::FINISH => $this->validateStartFinishConfig($config, $constraint),
            QuestStepType::CONNECT_TO_AP => $this->validateConnectToApConfig($config, $constraint),
            QuestStepType::WALK_TO => $this->validateWalkToConfig($config, $constraint),
            QuestStepType::SENSOR_CAPTURE => $this->validateSensorCaptureConfig($config, $constraint),
            QuestStepType::BLE_ADVERTISE => $this->validateBleAdvertiseConfig($config, $constraint),
        };
    }

    private function validateSensorCaptureConfig(array $config, ValidStepConfig $constraint): void
    {
        $type = QuestStepType::SENSOR_CAPTURE->value;

        // The module id is the only contract; the rest of the config is passed opaque to the module.
        $this->requireString($config, 'module', $type, $constraint);
    }

    private function validateBleAdvertiseConfig(array $config, ValidStepConfig $constraint): void
    {
        $type = QuestStepType::BLE_ADVERTISE->value;

        // Required: how long the frame must stay on air. The identity itself comes from the lab
        // bundle's advertise namespace, never from a quest config a participant can read.
        $this->requirePositiveInteger($config, 'duration_seconds', $type, $constraint);

        // Optional: commanded advertising interval. Android maps it onto AdvertiseSettings buckets
        // and iOS cannot set it at all, so it is a request, not a promise — but an impossible
        // value is still an authoring error.
        if (isset($config['adv_interval_ms'])) {
            $this->validateInteger($config, 'adv_interval_ms', $type, $constraint);
            if (is_int($config['adv_interval_ms'])
                && ($config['adv_interval_ms'] < 100 || $config['adv_interval_ms'] > 10240)) {
                $this->context->buildViolation($constraint->messageInvalidValue)
                    ->setParameter('{{ field }}', 'adv_interval_ms')
                    ->setParameter('{{ type }}', $type)
                    ->setParameter('{{ reason }}', 'must be between 100 and 10240 (BLE advertising interval bounds)')
                    ->addViolation();
            }
        }

        if (isset($config['tx_power'])) {
            $this->validateString($config, 'tx_power', $type, $constraint);
            $allowed = ['ultra_low', 'low', 'medium', 'high'];
            if (is_string($config['tx_power']) && !in_array($config['tx_power'], $allowed, true)) {
                $this->context->buildViolation($constraint->messageInvalidValue)
                    ->setParameter('{{ field }}', 'tx_power')
                    ->setParameter('{{ type }}', $type)
                    ->setParameter('{{ reason }}', 'must be one of: ultra_low, low, medium, high')
                    ->addViolation();
            }
        }
    }

    private function validateScanQrConfig(array $config, ValidStepConfig $constraint): void
    {
        $type = QuestStepType::SCAN_QR->value;

        // Required fields
        $this->requireString($config, 'expected_value', $type, $constraint);
        $this->requireString($config, 'location', $type, $constraint);
    }

    private function validateFindBleDeviceConfig(array $config, ValidStepConfig $constraint): void
    {
        $type = QuestStepType::FIND_BLE_DEVICE->value;

        // Required fields
        $this->requireString($config, 'device_name', $type, $constraint);

        // Optional: Validate MAC address format for device_id if provided
        if (isset($config['device_id']) && is_string($config['device_id'])) {
            if (!preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $config['device_id'])) {
                $this->context->buildViolation($constraint->messageInvalidMacAddress)
                    ->setParameter('{{ field }}', 'device_id')
                    ->setParameter('{{ type }}', $type)
                    ->addViolation();
            }
        }

        // Optional fields validation
        if (isset($config['rssi_threshold'])) {
            $this->validateInteger($config, 'rssi_threshold', $type, $constraint);
        }
        if (isset($config['detection_duration'])) {
            $this->validatePositiveInteger($config, 'detection_duration', $type, $constraint);
        }
    }

    private function validateWaitConfig(array $config, ValidStepConfig $constraint): void
    {
        $type = QuestStepType::WAIT->value;

        // Required fields
        $this->requirePositiveInteger($config, 'timeout_seconds', $type, $constraint);
    }

    private function validateStartFinishConfig(array $config, ValidStepConfig $constraint): void
    {
        // Start and Finish steps have no required config, but can have optional description
        // No validation needed for empty config
    }

    private function validateConnectToApConfig(array $config, ValidStepConfig $constraint): void
    {
        $type = QuestStepType::CONNECT_TO_AP->value;

        // Required fields
        $this->requireString($config, 'ssid', $type, $constraint);

        // Optional fields
        if (isset($config['password'])) {
            $this->validateString($config, 'password', $type, $constraint);
        }
    }

    private function validateWalkToConfig(array $config, ValidStepConfig $constraint): void
    {
        $type = QuestStepType::WALK_TO->value;

        // Required fields - at least location or coordinates
        $hasLocation = isset($config['location']) && is_string($config['location']) && !empty($config['location']);
        $hasCoordinates = isset($config['latitude']) && isset($config['longitude']);

        if (!$hasLocation && !$hasCoordinates) {
            $this->context->buildViolation($constraint->messageInvalidValue)
                ->setParameter('{{ field }}', 'location/coordinates')
                ->setParameter('{{ type }}', $type)
                ->setParameter('{{ reason }}', 'either "location" or "latitude" and "longitude" must be provided')
                ->addViolation();
        }

        if ($hasCoordinates) {
            $this->validateNumber($config, 'latitude', $type, $constraint);
            $this->validateNumber($config, 'longitude', $type, $constraint);

            // Validate latitude range
            if (isset($config['latitude']) && is_numeric($config['latitude'])) {
                $lat = (float)$config['latitude'];
                if ($lat < -90 || $lat > 90) {
                    $this->context->buildViolation($constraint->messageInvalidValue)
                        ->setParameter('{{ field }}', 'latitude')
                        ->setParameter('{{ type }}', $type)
                        ->setParameter('{{ reason }}', 'must be between -90 and 90')
                        ->addViolation();
                }
            }

            // Validate longitude range
            if (isset($config['longitude']) && is_numeric($config['longitude'])) {
                $lng = (float)$config['longitude'];
                if ($lng < -180 || $lng > 180) {
                    $this->context->buildViolation($constraint->messageInvalidValue)
                        ->setParameter('{{ field }}', 'longitude')
                        ->setParameter('{{ type }}', $type)
                        ->setParameter('{{ reason }}', 'must be between -180 and 180')
                        ->addViolation();
                }
            }
        }

        // Optional radius validation
        if (isset($config['radius'])) {
            $this->validatePositiveNumber($config, 'radius', $type, $constraint);
        }
    }

    private function requireString(array $config, string $field, string $type, ValidStepConfig $constraint): void
    {
        if (!isset($config[$field])) {
            $this->context->buildViolation($constraint->messageMissingField)
                ->setParameter('{{ field }}', $field)
                ->setParameter('{{ type }}', $type)
                ->addViolation();
            return;
        }

        if (!is_string($config[$field])) {
            $this->context->buildViolation($constraint->messageInvalidFieldType)
                ->setParameter('{{ field }}', $field)
                ->setParameter('{{ type }}', $type)
                ->setParameter('{{ expected }}', 'a string')
                ->setParameter('{{ actual }}', gettype($config[$field]))
                ->addViolation();
            return;
        }

        if (empty(trim($config[$field]))) {
            $this->context->buildViolation($constraint->messageInvalidValue)
                ->setParameter('{{ field }}', $field)
                ->setParameter('{{ type }}', $type)
                ->setParameter('{{ reason }}', 'cannot be empty')
                ->addViolation();
        }
    }

    private function validateString(array $config, string $field, string $type, ValidStepConfig $constraint): void
    {
        if (!is_string($config[$field])) {
            $this->context->buildViolation($constraint->messageInvalidFieldType)
                ->setParameter('{{ field }}', $field)
                ->setParameter('{{ type }}', $type)
                ->setParameter('{{ expected }}', 'a string')
                ->setParameter('{{ actual }}', gettype($config[$field]))
                ->addViolation();
        }
    }

    private function requirePositiveInteger(array $config, string $field, string $type, ValidStepConfig $constraint): void
    {
        if (!isset($config[$field])) {
            $this->context->buildViolation($constraint->messageMissingField)
                ->setParameter('{{ field }}', $field)
                ->setParameter('{{ type }}', $type)
                ->addViolation();
            return;
        }

        if (!is_int($config[$field])) {
            $this->context->buildViolation($constraint->messageInvalidFieldType)
                ->setParameter('{{ field }}', $field)
                ->setParameter('{{ type }}', $type)
                ->setParameter('{{ expected }}', 'an integer')
                ->setParameter('{{ actual }}', gettype($config[$field]))
                ->addViolation();
            return;
        }

        if ($config[$field] <= 0) {
            $this->context->buildViolation($constraint->messageInvalidValue)
                ->setParameter('{{ field }}', $field)
                ->setParameter('{{ type }}', $type)
                ->setParameter('{{ reason }}', 'must be a positive integer')
                ->addViolation();
        }
    }

    private function validateInteger(array $config, string $field, string $type, ValidStepConfig $constraint): void
    {
        if (!is_int($config[$field])) {
            $this->context->buildViolation($constraint->messageInvalidFieldType)
                ->setParameter('{{ field }}', $field)
                ->setParameter('{{ type }}', $type)
                ->setParameter('{{ expected }}', 'an integer')
                ->setParameter('{{ actual }}', gettype($config[$field]))
                ->addViolation();
        }
    }

    private function validatePositiveInteger(array $config, string $field, string $type, ValidStepConfig $constraint): void
    {
        if (!is_int($config[$field])) {
            $this->context->buildViolation($constraint->messageInvalidFieldType)
                ->setParameter('{{ field }}', $field)
                ->setParameter('{{ type }}', $type)
                ->setParameter('{{ expected }}', 'an integer')
                ->setParameter('{{ actual }}', gettype($config[$field]))
                ->addViolation();
            return;
        }

        if ($config[$field] <= 0) {
            $this->context->buildViolation($constraint->messageInvalidValue)
                ->setParameter('{{ field }}', $field)
                ->setParameter('{{ type }}', $type)
                ->setParameter('{{ reason }}', 'must be a positive integer')
                ->addViolation();
        }
    }

    private function validateNumber(array $config, string $field, string $type, ValidStepConfig $constraint): void
    {
        if (!is_numeric($config[$field])) {
            $this->context->buildViolation($constraint->messageInvalidFieldType)
                ->setParameter('{{ field }}', $field)
                ->setParameter('{{ type }}', $type)
                ->setParameter('{{ expected }}', 'a number')
                ->setParameter('{{ actual }}', gettype($config[$field]))
                ->addViolation();
        }
    }

    private function validatePositiveNumber(array $config, string $field, string $type, ValidStepConfig $constraint): void
    {
        if (!is_numeric($config[$field])) {
            $this->context->buildViolation($constraint->messageInvalidFieldType)
                ->setParameter('{{ field }}', $field)
                ->setParameter('{{ type }}', $type)
                ->setParameter('{{ expected }}', 'a number')
                ->setParameter('{{ actual }}', gettype($config[$field]))
                ->addViolation();
            return;
        }

        if ((float)$config[$field] <= 0) {
            $this->context->buildViolation($constraint->messageInvalidValue)
                ->setParameter('{{ field }}', $field)
                ->setParameter('{{ type }}', $type)
                ->setParameter('{{ reason }}', 'must be a positive number')
                ->addViolation();
        }
    }
}
