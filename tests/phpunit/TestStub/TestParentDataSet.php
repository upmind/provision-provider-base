<?php

namespace Upmind\ProvisionBase\TestStub;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * @property-read TestNameDataSet $child
 * @property-read TestNameDataSet[] $children
 */
class TestParentDataSet extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'child' => ['required', TestNameDataSet::class],
            'children' => ['array'],
            'children.*' => [TestNameDataSet::class],
        ]);
    }
}
