<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Provider\Contract;

use Upmind\ProvisionBase\Provider\DataSet\SystemInfo;

/**
 * Marker interface used to indicate Providers which need access to system info.
 */
interface HasSystemInfo
{
    /**
     * Sets the SystemInfo instance.
     */
    public function setSystemInfo(SystemInfo $systemInfo): void;

    /**
     * Get SystemInfo instance containing metadata about the system/runtime environment.
     */
    public function getSystemInfo(): SystemInfo;
}
