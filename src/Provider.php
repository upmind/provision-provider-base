<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase;

use InvalidArgumentException;
use RuntimeException;
use Upmind\ProvisionBase\Exception\InvalidProviderJob;
use Upmind\ProvisionBase\Provider\Contract\ProviderInterface;
use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Registry\Data\ProviderRegister;

/**
 * Provider wrapper class encapsulating a provision provider register and instance.
 */
class Provider
{
    /**
     * @var ProviderRegister
     */
    protected $register;

    /**
     * @var ProviderInterface
     */
    protected $instance;

    /**
     * @param ProviderRegister $register Provider register
     * @param ProviderInterface $instance Provider instance
     */
    public function __construct(ProviderRegister $register, ProviderInterface $instance)
    {
        $this->register = $register;
        $this->instance = $instance;

        if ($register->getClass() !== get_class($instance)) {
            throw new InvalidArgumentException(
                'The given provider register class does not match the given provider instance'
            );
        }
    }

    /**
     * Get the provider register.
     */
    public function getRegister(): ProviderRegister
    {
        return $this->register;
    }

    /**
     * Get the provider instance.
     */
    public function getInstance(): ProviderInterface
    {
        if (!isset($this->instance)) {
            throw new RuntimeException('Provider instance has been unset');
        }

        return $this->instance;
    }

    /**
     * Unset the provider instance (e.g, to trigger destructors).
     */
    public function unsetInstance(): void
    {
        unset($this->instance);
    }

    /**
     * Create a provider job instance to execute the given provision function.
     *
     * @param string $function Provision function name
     * @param array|DataSet $parameterData Provision function parameters
     *
     * @throws InvalidProviderJob If the requested function is not supported
     */
    public function makeJob(string $function, $parameterData): ProviderJob
    {
        return new ProviderJob($this, $function, $parameterData);
    }
}
