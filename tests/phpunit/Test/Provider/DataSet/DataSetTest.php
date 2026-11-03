<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Provider\DataSet;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Exception\InvalidDataSetException;
use Upmind\ProvisionBase\Provider\Contract\DataSetInterface;
use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\ResultData;
use Upmind\ProvisionBase\Provider\DataSet\RuleParser;
use Upmind\ProvisionBase\TestStub\TestNameDataSet;
use Upmind\ProvisionBase\TestStub\TestParentDataSet;
use Upmind\ProvisionBase\TestStub\TestSystemInfo;

class DataSetTest extends TestCase
{
    protected function setUp(): void
    {
        $factory = new Factory(new Translator(new ArrayLoader(), 'en'));

        DataSet::setValidatorFactory($factory);
        RuleParser::setValidator($factory->make([], []));
    }

    protected function tearDown(): void
    {
        DataSet::setValidatorFactory(null);
    }

    public function testConstructorSignatureIsDeclaredByInterface(): void
    {
        $constructor = new \ReflectionMethod(DataSet::class, '__construct');

        $this->assertInstanceOf(DataSetInterface::class, new TestNameDataSet());
        $this->assertSame(DataSetInterface::class, $constructor->getPrototype()->getDeclaringClass()->getName());
        $this->assertCount(1, $constructor->getParameters());
    }

    public function testCreateReturnsInstanceOfCalledClass(): void
    {
        $dataSet = TestNameDataSet::create(['name' => 'foo']);

        $this->assertInstanceOf(TestNameDataSet::class, $dataSet);
        $this->assertSame('foo', $dataSet->name);
    }

    public function testAutoValidationIsEnabledByDefault(): void
    {
        $this->assertTrue((new TestNameDataSet())->autoValidation());
        $this->assertTrue(TestNameDataSet::create()->autoValidation());
    }

    public function testAccessValidatesWhenAutoValidationIsEnabled(): void
    {
        $dataSet = TestNameDataSet::create(['name' => 123]);

        $this->expectException(InvalidDataSetException::class);

        $dataSet->get('name');
    }

    public function testAccessSkipsValidationWhenAutoValidationIsDisabled(): void
    {
        $dataSet = TestNameDataSet::create(['name' => 123]);
        $dataSet->autoValidation(false);

        $this->assertSame(123, $dataSet->get('name'));
        $this->assertFalse($dataSet->isValidated());
    }

    public function testNestedDataSetsHaveAutoValidationDisabled(): void
    {
        $dataSet = TestParentDataSet::create([
            'child' => ['name' => 123],
            'children' => [['name' => 456]],
        ]);
        $dataSet->autoValidation(false);

        $this->assertTrue(TestParentDataSet::create()->autoValidation());

        $this->assertInstanceOf(TestNameDataSet::class, $dataSet->child);
        $this->assertFalse($dataSet->child->autoValidation());
        $this->assertSame(123, $dataSet->child->name);

        $this->assertInstanceOf(TestNameDataSet::class, $dataSet->children[0]);
        $this->assertFalse($dataSet->children[0]->autoValidation());
        $this->assertSame(456, $dataSet->children[0]->name);
    }

    public function testSubclassConstructorCanDisableAutoValidation(): void
    {
        $this->assertFalse(TestSystemInfo::create()->autoValidation());
    }

    public function testResultDataSetDataKeepsAutoValidationSetting(): void
    {
        $resultData = ResultData::create();
        $resultData->autoValidation(false);

        $resultData->setData(['foo' => 'bar']);

        $this->assertFalse($resultData->autoValidation());
        $this->assertSame(['foo' => 'bar'], $resultData->getData());
    }
}
