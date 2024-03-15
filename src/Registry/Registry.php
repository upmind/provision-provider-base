<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Registry;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Upmind\ProvisionBase\Registry\Data\BufferedRegister;
use Upmind\ProvisionBase\Registry\Data\CategoryRegister;
use Upmind\ProvisionBase\Registry\Data\ProviderRegister;

/**
 * Registry of available provision categories, providers and functions.
 */
class Registry
{
    /**
     * Registered Categories.
     *
     * @var Collection<CategoryRegister>|CategoryRegister[]
     */
    protected $categories;

    /**
     * Buffered "register" calls. If the registry was loaded from cache, attempts
     * to register Categories and Providers are stored in this buffer instead.
     *
     * @var Collection<BufferedRegister>|BufferedRegister[]
     */
    protected $buffer;

    /**
     * Whether or not this instance came from cache.
     *
     * @var bool
     */
    protected $wasCached = false;

    /**
     * Whether or not provision data has yet been enumerated.
     *
     * @var bool
     */
    protected $dataEnumerated = false;

    /**
     * Singleton instance.
     *
     * @var self
     */
    protected static $instance;

    /**
     * Get the singleton Registry instance.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Set the singleton Registry instance.
     *
     * @return void
     */
    public static function setInstance(self $registry): void
    {
        self::$instance = $registry;
    }

    /**
     * Determine whether or not the registry was cached.
     */
    public function wasCached(): bool
    {
        return $this->wasCached;
    }

    /**
     * Register a provision category. If registry was loaded from cache, this
     * method will call will be buffered instead, and will return null.
     *
     * @param string $identifier Unique category identifier
     * @param string $class Fully-namespaced category class
     *
     * @return CategoryRegister|null Registered category, if successful
     */
    public function registerCategory(string $identifier, string $class): ?CategoryRegister
    {
        if ($this->wasCached()) {
            $this->getBuffer()->push(new BufferedRegister($this, __METHOD__, func_get_args()));
            return null;
        }

        if ($this->hasCategory($identifier) || $this->hasCategory($class)) {
            throw new InvalidArgumentException(
                sprintf('Provision category %s already registered in the application', $identifier)
            );
        }

        $this->dataEnumerated = false;

        $this->getCategories()->push($category = new CategoryRegister($identifier, $class));
        return $category;
    }

    /**
     * Register a provision provider. If registry was loaded from cache, this
     * method will call will be buffered instead, and will return null.
     *
     * @param string|CategoryRegister $category Category identifier, class or register instance
     * @param string $identifier Unique provider identifier
     * @param string $class Fully-namespaced provider class
     *
     * @return ProviderRegister|null Whether or not the provider was registered
     */
    public function registerProvider($category, string $identifier, string $class): ?ProviderRegister
    {
        if ($this->wasCached()) {
            $this->getBuffer()->push(new BufferedRegister($this, __METHOD__, func_get_args()));
            return null;
        }

        if (!$this->hasCategory($category)) {
            throw new InvalidArgumentException(
                sprintf('Provision category %s is not yet registered in the application', (string)$category)
            );
        }

        $category = $this->getCategory($category);

        if ($category->hasProvider($identifier) || $category->hasProvider($class)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Provision category %s already has provider %s registered',
                    $category->getIdentifier(),
                    $identifier
                )
            );
        }

        $this->dataEnumerated = false;

        return $category->addProvider($identifier, $class);
    }

    /**
     * Determine whether the given category has been registered.
     *
     * @param string|CategoryRegister $category Category identifier, class or register instance
     *
     * @return bool
     */
    public function hasCategory($category): bool
    {
        $this->enumerateData();

        return $this->getCategories()
            ->contains(function (CategoryRegister $register) use ($category) {
                return $register->is($category);
            });
    }

    /**
     * Get the given category from the registry.
     *
     * @param string|CategoryRegister $category Category identifier, class or register instance
     *
     * @return CategoryRegister|null Registered category, if found
     */
    public function getCategory($category): ?CategoryRegister
    {
        $this->enumerateData();

        return $this->getCategories()
            ->first(function (CategoryRegister $register) use ($category) {
                return $register->is($category);
            });
    }

    /**
     * Determine whether the given provider has been registered.
     *
     * @param string|CategoryRegister $category Category identifier, class or register instance
     * @param string|ProviderRegister $provider Provider identifier, class or register instance
     *
     * @return bool
     */
    public function hasProvider($category, $provider): bool
    {
        $this->enumerateData();

        if (!$category = $this->getCategory($category)) {
            return false;
        }

        return $category->hasProvider($provider);
    }

    /**
     * Get the given provider from the registry.
     *
     * @param string|CategoryRegister $category Category identifier, class or register instance
     * @param string|ProviderRegister $provider Provider identifier, class or register instance
     *
     * @return ProviderRegister|null Registered provider, if found
     */
    public function getProvider($category, $provider): ?ProviderRegister
    {
        $this->enumerateData();

        if (!$category = $this->getCategory($category)) {
            return null;
        }

        return $category->getProvider($provider);
    }

    /**
     * Get all categories from the registry.
     *
     * @return Collection<array-key, CategoryRegister>
     */
    public function getCategories(): Collection
    {
        $this->enumerateData();

        if (!isset($this->categories)) {
            $this->categories = new Collection();
        }

        return $this->categories;
    }

    /**
     * Enumerate all category register and sub-register data.
     */
    public function enumerateData(): void
    {
        if ($this->dataEnumerated) {
            return;
        }

        $this->dataEnumerated = true;

        $this->getCategories()->each(function (CategoryRegister $category) {
            $category->enumerateData();
        });
    }

    /**
     * Get all buffered register calls.
     *
     * @return Collection<array-key, BufferedRegister>
     */
    public function getBuffer(): Collection
    {
        if (!isset($this->buffer)) {
            $this->buffer = new Collection();
        }

        return $this->buffer;
    }

    /**
     * Rebuild the registry from buffered register calls.
     */
    protected function rebuildFromBuffer()
    {
        $this->wasCached = false;
        $this->categories = null;

        $this->getBuffer()
            ->each(function (BufferedRegister $buffer) {
                $buffer->execute();
            });

        $this->buffer = null;
    }

    public function __sleep(): array
    {
        if ($this->wasCached()) {
            $this->rebuildFromBuffer();
        }

        $this->enumerateData();

        return [
            'categories',
        ];
    }

    public function __wakeup()
    {
        $this->wasCached = true;
    }

    public function __debugInfo()
    {
        $return = [
            'categories' => $this->categories ? $this->categories->all() : null,
            'cached' => $this->wasCached,
        ];

        if ($this->wasCached) {
            $return['buffer'] = $this->buffer ? $this->buffer->all() : null;
        }

        return $return;
    }
}
