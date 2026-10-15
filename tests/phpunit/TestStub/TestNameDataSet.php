<?php

namespace Upmind\ProvisionBase\TestStub;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * @property-read string $name
 */
class TestNameDataSet extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'name' => ['required', 'string'],
        ]);
    }
}
