<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Registry\Data\Contract;

use Illuminate\Support\Collection;
use Upmind\ProvisionBase\Provider\DataSet\AboutData;
use Upmind\ProvisionBase\Registry\Data\FunctionRegister;
use Upmind\ProvisionBase\Registry\Data\RegisterInterface;

/**
 * Register for a provision category.
 */
interface CategoryRegisterInterface extends RegisterInterface
{
    /**
     * Return the category identifier.
     */
    public function getIdentifier(): string;

    /**
     * Return the category class.
     */
    public function getClass(): string;

    /**
     * Return the category about data.
     */
    public function getAbout(): AboutData;

    /**
     * Return the provider registers of this category.
     *
     * @return Collection<array-key, ProviderRegisterInterface>
     */
    public function getProviders(): Collection;

    /**
     * Return a provider by identifier, class or register instance.
     *
     * @param string|ProviderRegisterInterface $provider Provider identifier, class or register instance
     */
    public function getProvider($provider): ?ProviderRegisterInterface;

    /**
     * Add a provider to this category.
     *
     * @param string $identifier Provider unique identifier
     * @param string $class Provider fully-namespaced class name
     */
    public function addProvider(string $identifier, string $class): ProviderRegisterInterface;

    /**
     * Determine whether this category has the given provider.
     *
     * @param string|ProviderRegisterInterface $provider Provider identifier, class or register instance
     */
    public function hasProvider($provider): bool;

    /**
     * Return the provision functions of this category.
     *
     * @return Collection<array-key, FunctionRegister>
     */
    public function getFunctions(): Collection;

    /**
     * Return the given function.
     *
     * @param string|FunctionRegister $function Function name or register instance
     */
    public function getFunction($function): ?FunctionRegister;

    /**
     * Determine whether this category has the given function.
     *
     * @param string|FunctionRegister $function Function name or register instance
     */
    public function hasFunction($function): bool;
}
