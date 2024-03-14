<?php

namespace Upmind\ProvisionBase\Laravel\Validation;

use Illuminate\Support\Str;
use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\RuleParser;

class ValidationHelper
{
    /**
     * Determine whether the system supports the given validation rule.
     */
    public static function ruleExists(string $rule): bool
    {
        [$rule, $parameters] = RuleParser::parseRule($rule);

        $validator = DataSet::getValidatorFactory()->make([], []);

        if (method_exists($validator, sprintf('validate%s', Str::studly($rule)))) {
            return true; //built-in rule
        }

        if (isset($validator->extensions[strtolower($rule)])) {
            return true; //custom rule
        }

        return false;
    }

    /**
     * Convert a short/abbreviated data type to full type e.g., int --> integer.
     */
    public static function shortToLongType(string $type): string
    {
        switch ($type = strtolower($type)) {
            case 'int':
                return 'integer';
            case 'str':
                return 'string';
            case 'bool':
                return 'boolean';
            default:
                return $type;
        }
    }
}
