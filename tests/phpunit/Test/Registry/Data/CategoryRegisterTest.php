<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Registry\Data;

use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Registry\Data\CategoryRegister;
use Upmind\ProvisionBase\TestStub\TestCategory;

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
}
