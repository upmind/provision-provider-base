<?php

namespace Upmind\ProvisionBase\TestStub;

use Upmind\ProvisionBase\Provider\Contract\CategoryInterface;
use Upmind\ProvisionBase\Provider\DataSet\AboutData;

abstract class TestCategory implements CategoryInterface
{
    public static function aboutCategory(): AboutData
    {
        return new AboutData([
            'name' => 'Test Category',
            'description' => 'A test category'
        ]);
    }
}
