<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Registry\Data;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Upmind\ProvisionBase\Registry\Data\ProviderRegister;
use Upmind\ProvisionBase\Registry\Data\CategoryRegister;
use Upmind\ProvisionBase\Registry\Data\FunctionRegister;
use Upmind\ProvisionBase\TestStub\AbstractTestProvider;
use Upmind\ProvisionBase\TestStub\TestProvider;

class ProviderRegisterTest extends TestCase
{
    private MockObject|CategoryRegister $categoryRegister;
    private ProviderRegister $register;

    protected function setUp(): void
    {
        $this->categoryRegister = $this->createMock(CategoryRegister::class);
        $this->categoryRegister->expects($this->any())->method('getClass')->willReturn(AbstractTestProvider::class);
        $this->register = new ProviderRegister('test-provider', TestProvider::class, $this->categoryRegister);
    }

    public function testGetCategoryReturnsParentCategory(): void
    {
        $parentCategory = $this->register->getCategory();
        $this->assertEquals($this->categoryRegister, $parentCategory);
    }

    public function testGetConstructorReturnsConstructorRegister(): void
    {
        $constructor = $this->register->getConstructor();

        $this->assertEquals('__construct', $constructor->getName());
    }

    public function testHasAndGetDelegateToCategory(): void
    {
        $functionRegister = $this->createMock(FunctionRegister::class);

        $this->categoryRegister->method('hasFunction')->with('testFunction')->willReturn(true);
        $this->categoryRegister->method('getFunction')->with('testFunction')->willReturn($functionRegister);

        $this->assertTrue($this->register->hasFunction('testFunction'));
        $this->assertSame($functionRegister, $this->register->getFunction('testFunction'));
    }
}
