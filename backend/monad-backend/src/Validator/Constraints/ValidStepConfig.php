<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ValidStepConfig extends Constraint
{
    public string $messageInvalidType = 'Invalid step type "{{ type }}".';
    public string $messageMissingField = 'Missing required field "{{ field }}" for step type "{{ type }}".';
    public string $messageInvalidFieldType = 'Field "{{ field }}" must be {{ expected }}, {{ actual }} given for step type "{{ type }}".';
    public string $messageInvalidMacAddress = 'Field "{{ field }}" must be a valid MAC address (format: XX:XX:XX:XX:XX:XX) for step type "{{ type }}".';
    public string $messageInvalidValue = 'Field "{{ field }}" has invalid value for step type "{{ type }}": {{ reason }}.';

    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
