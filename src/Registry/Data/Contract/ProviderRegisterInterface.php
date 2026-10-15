<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Registry\Data\Contract;

use Illuminate\Support\Collection;
use Upmind\ProvisionBase\Provider\DataSet\AboutData;
use Upmind\ProvisionBase\Registry\Data\FunctionRegister;
use Upmind\ProvisionBase\Registry\Data\RegisterInterface;

/**
 * Register for a provision provider.
 */
interface ProviderRegisterInterface extends RegisterInterface
{
    /**
     * Return the provider identifier.
     */
    public function getIdentifier(): string;

    /**
     * Return the provider class.
     */
    public function getClass(): string;

    /**
     * Return the provider about data.
     */
    public function getAbout(): AboutData;

    /**
     * Return the parent category register.
     */
    public function getCategory(): CategoryRegisterInterface;

    /**
     * Return the provider constructor register.
     */
    public function getConstructor(): FunctionRegister;

    /**
     * Return the provision functions of this provider.
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
     * Determine whether this provider has the given function.
     *
     * @param string|FunctionRegister $function Function name or register instance
     */
    public function hasFunction($function): bool;
}
