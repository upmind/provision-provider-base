<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Registry\Data;

use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Registry\Data\CategoryRegister;
use Upmind\ProvisionBase\Registry\Data\Contract\CategoryRegisterInterface;
use Upmind\ProvisionBase\Registry\Data\Contract\ProviderRegisterInterface;
use Upmind\ProvisionBase\TestStub\AbstractTestProvider;
use Upmind\ProvisionBase\TestStub\TestCategory;
use Upmind\ProvisionBase\TestStub\TestProvider;

class CategoryRegisterTest extends TestCase
{
    private CategoryRegister $register;

    protected string $class = TestCategory::class;

    protected function setUp(): void
    {
        $this->register = new CategoryRegister('test-category', TestCategory::class);
    }

    public function testConstructorCreatesRegisterSuccessfully(): void
    {
        $this->assertInstanceOf(CategoryRegisterInterface::class, $this->register);
        $this->assertSame('test-category', $this->register->getIdentifier());
        $this->assertSame(TestCategory::class, $this->register->getClass());
    }

    public function testGetProvidersReturnsEmptyCollection(): void
    {
        $providers = $this->register->getProviders();
        $this->assertCount(0, $providers);
    }

    public function testHasProviderReturnsFalseForNonExistentProvider(): void
    {
        $this->assertFalse($this->register->hasProvider('non-existent'));
    }

    public function testGetProviderReturnsNullForNonExistentProvider(): void
    {
        $this->assertNull($this->register->getProvider('non-existent'));
    }

    public function testGetFunctionsReturnsEmptyCollection(): void
    {
        $functions = $this->register->getFunctions();
        $this->assertCount(0, $functions);
    }

    public function testAddProviderReturnsProviderRegister(): void
    {
        $category = new CategoryRegister('test-category', AbstractTestProvider::class);

        $provider = $category->addProvider('test-provider', TestProvider::class);

        $this->assertInstanceOf(ProviderRegisterInterface::class, $provider);
        $this->assertSame($provider, $category->getProvider('test-provider'));
        $this->assertSame($category, $provider->getCategory());
    }
}
