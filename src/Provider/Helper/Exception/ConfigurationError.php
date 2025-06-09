<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Provider\Helper\Exception;

use Upmind\ProvisionBase\Provider\Helper\Exception\Contract\ProviderError;

class ConfigurationError extends \RuntimeException implements ProviderError
{
    final public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function forMissingData(array $fieldNames): ProviderError
    {
        return new static("Configuration data missing: " . implode(', ', $fieldNames));
    }
}
