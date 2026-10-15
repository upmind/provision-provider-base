<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Exception;

use Illuminate\Contracts\Support\Arrayable;
use Throwable;

/**
 * Exception representing a critical error encountered during provision function
 * execution which needs immediate remediation.
 */
class CriticalProvisionProviderError extends ProviderJobError
{
    //
}
