<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Registry\Data;

use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Provider\DataSet\EmptyData;
use Upmind\ProvisionBase\Provider\DataSet\ResultData;
use Upmind\ProvisionBase\Registry\Data\Contract\CategoryRegisterInterface;
use Upmind\ProvisionBase\Registry\Data\Contract\ProviderRegisterInterface;
use Upmind\ProvisionBase\Registry\Data\FunctionRegister;
use Upmind\ProvisionBase\TestStub\TestDataSet;

class FunctionRegisterTest extends TestCase
{
    public function testCategoryFunctionReturnsCategoryParent(): void
    {
        $category = $this->createMock(CategoryRegisterInterface::class);

        $function = new FunctionRegister('testFunction', TestDataSet::class, null, $category);

        $this->assertSame($category, $function->getParent());
        $this->assertSame($category, $function->getCategory());
        $this->assertFalse($function->isConstructor());
        $this->assertSame(TestDataSet::class, $function->getParameter()->getClass());
        $this->assertSame(ResultData::class, $function->getReturn()->getClass());
    }

    public function testProviderConstructorReturnsCategoryOfProvider(): void
    {
        $category = $this->createMock(CategoryRegisterInterface::class);
        $provider = $this->createMock(ProviderRegisterInterface::class);
        $provider->method('getCategory')->willReturn($category);

        $function = new FunctionRegister('__construct', TestDataSet::class, null, $provider);

        $this->assertSame($provider, $function->getParent());
        $this->assertSame($category, $function->getCategory());
        $this->assertTrue($function->isConstructor());
        $this->assertSame(EmptyData::class, $function->getReturn()->getClass());
    }
}
