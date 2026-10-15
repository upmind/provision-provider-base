<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Exception;

use Throwable;
use Upmind\ProvisionBase\ConnectionErrorClassifier;

/**
 * Exception representing a failed connection to a provider API during provision
 * function execution.
 */
class ProviderConnectionError extends ProvisionFunctionError
{
    /**
     * @param Throwable $e Exception thrown by the provision function
     * @param Throwable $connectionError Connection error in the chain of $e
     */
    public static function fromConnectionError(Throwable $e, Throwable $connectionError): self
    {
        $error = new self('Provider API connection failed', 0, $e);
        $error->withDebug(['connection_error' => ConnectionErrorClassifier::getDebugData($connectionError)]);

        return $error;
    }
}
