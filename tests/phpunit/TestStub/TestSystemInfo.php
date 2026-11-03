<?php

namespace Upmind\ProvisionBase\TestStub;

use Upmind\ProvisionBase\Provider\DataSet\SystemInfo;

class TestSystemInfo extends SystemInfo
{
    public function __construct($values = [])
    {
        parent::__construct($values);

        $this->autoValidation(false);
    }

    protected function fillNestedDataSets(): void
    {
    }
}
