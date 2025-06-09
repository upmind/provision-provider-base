<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Registry;

use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Registry\Registry;

class RegistryTest extends TestCase
{
    private Registry $registry;

    protected function setUp(): void
    {
        $this->registry = new Registry();
    }

    public function testGetInstanceReturnsSingletonInstance(): void
    {
        $instance1 = Registry::getInstance();
        $instance2 = Registry::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function testSetInstanceUpdatesSingleton(): void
    {
        $newRegistry = new Registry();
        Registry::setInstance($newRegistry);

        $this->assertSame($newRegistry, Registry::getInstance());
    }

    public function testWasCachedReturnsFalseByDefault(): void
    {
        $this->assertFalse($this->registry->wasCached());
    }

    public function testGetCategoriesReturnsEmptyCollection(): void
    {
        $categories = $this->registry->getCategories();

        $this->assertCount(0, $categories);
    }

    public function testHasCategoryReturnsFalseForNonExistentCategory(): void
    {
        $this->assertFalse($this->registry->hasCategory('non-existent'));
    }

    public function testGetCategoryReturnsNullForNonExistentCategory(): void
    {
        $this->assertNull($this->registry->getCategory('non-existent'));
    }

    public function testHasProviderReturnsFalseForNonExistentProvider(): void
    {
        $this->assertFalse($this->registry->hasProvider('category', 'provider'));
    }

    public function testGetProviderReturnsNullForNonExistentProvider(): void
    {
        $this->assertNull($this->registry->getProvider('category', 'provider'));
    }
}
