<?php


namespace Upmind\ProvisionBase\TestStub;

use Upmind\ProvisionBase\Provider\DataSet\SystemInfo;

class TestSystemInfo extends SystemInfo
{
    public function __construct($values = [], bool $autoValidation = true)
    {
        parent::__construct($values, false);
    }

    protected function fillNestedDataSets(): void
    {
    }
}
