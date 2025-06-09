<?php

namespace Upmind\ProvisionBase\TestStub;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

class TestDataSet extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([]);
    }

    public function isValidated(): bool
    {
        return true;
    }
}
