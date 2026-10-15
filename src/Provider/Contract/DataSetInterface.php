<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Provider\Contract;

/**
 * Data set contract. Fixes the constructor signature so data sets can be
 * created from a class name or `new static`.
 */
interface DataSetInterface
{
    /**
     * @param mixed[] $values Raw data
     */
    public function __construct($values = []);
}
