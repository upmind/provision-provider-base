<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Exception;

use Upmind\ProvisionBase\Exception\Contract\ProvisionException;

/**
 * A data set uses a validation rule which is not supported.
 */
final class UnsupportedValidationRule extends \LogicException implements ProvisionException
{
    public static function forUnsupportedRule(string $rule): self
    {
        return new self("Validation rule {$rule} is not supported");
    }

    public static function forBlockedRule(string $rule, string $reason): self
    {
        return new self("Validation rule {$rule} is blocked because it {$reason}");
    }

    public static function forBlockedParameter(string $rule, string $parameter, string $reason): self
    {
        return new self("Validation rule {$rule}:{$parameter} is blocked because it {$reason}");
    }
}
