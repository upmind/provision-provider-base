<?php

namespace Upmind\ProvisionBase\TestStub;

use RuntimeException;
use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * Data set which throws on construction.
 */
class TestThrowingDataSet extends DataSet
{
    public static function rules(): Rules
    {
        throw new RuntimeException('Data set failure');
    }
}
