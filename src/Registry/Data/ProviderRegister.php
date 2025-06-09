<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Registry\Data;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Upmind\ProvisionBase\Provider\Contract\ProviderInterface;
use Upmind\ProvisionBase\Provider\DataSet\AboutData;

/**
 * Register for a provision provider.
 */
class ProviderRegister extends ClassRegister
{
    /**
     * About data.
     *
     * @var AboutData|null
     */
    protected $about;

    /**
     * @var FunctionRegister|null
     */
    protected $constructor;

    /**
     * @param string $identifier Unique identifier
     * @param string $class Fully-namespaced class name
     *
     * @see `self::IDENTIFIER_REGEX`
     */
    public function __construct(string $identifier, string $class, CategoryRegister $category)
    {
        parent::__construct(...func_get_args());
    }

    public function getAbout(): AboutData
    {
        if (!isset($this->about)) {
            $this->about = $this->class::aboutProvider();
        }

        return $this->about;
    }

    /**
     * Get the provider's constructor register.
     */
    public function getConstructor(): FunctionRegister
    {
        if (!isset($this->constructor)) {
            if ($constructor = $this->getReflection()->getConstructor()) {
                $dataSetClass = $this->getMethodParameterDataSetClass($constructor);
            }

            $this->constructor = new FunctionRegister($constructor->getName(), $dataSetClass ?? null, null, $this);
        }

        return $this->constructor;
    }

    /**
     * @inheritDoc
     */
    public function checkClass(): void
    {
        if (!$this->getReflection()->isInstantiable()) {
            throw new InvalidArgumentException(sprintf('%s must be instantiable', $this->getClass()));
        }
    }

    /**
     * Enumerate provider about data and constructor data.
     */
    public function enumerateData(): void
    {
        $this->getAbout()->validateIfNotYetValidated();

        $this->getConstructor()->enumerateData();
    }

    /**
     * Return the parent category register.
     */
    public function getCategory(): CategoryRegister
    {
        return $this->getParent();
    }

    /**
     * Return the available provision functions.
     *
     * @return Collection<FunctionRegister>|FunctionRegister[]
     */
    public function getFunctions(): Collection
    {
        return $this->getCategory()->getFunctions();
    }

    /**
     * Determine whether this provision provider can execute the given provision function.
     *
     * @param string|FunctionRegister $function Function name or register
     */
    public function hasFunction($function): bool
    {
        return $this->getCategory()->hasFunction($function);
    }

    /**
     * Return the given function register.
     *
     * @param string|FunctionRegister $function Function name or register
     */
    public function getFunction($function): ?FunctionRegister
    {
        return $this->getCategory()->getFunction($function);
    }

    /**
     * @inheritDoc
     */
    protected function getInterface(): string
    {
        return ProviderInterface::class;
    }

    /**
     * @inheritDoc
     */
    protected function checkRegister(): void
    {
        parent::checkRegister();

        if (!$this->hasParent()) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s %s must have a parent %s',
                    class_basename($this),
                    $this->getIdentifier(),
                    class_basename(CategoryRegister::class)
                )
            );
        }
    }

    /**
     * @inheritDoc
     */
    protected function checkParent(ClassRegister $parent): void
    {
        if (!$parent instanceof CategoryRegister) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s %s parent must be of type %s',
                    class_basename($this),
                    $this->getIdentifier(),
                    class_basename(CategoryRegister::class)
                )
            );
        }

        parent::checkParent($parent);
    }

    /**
     * @inheritDoc
     */
    protected function checkChild(ClassRegister $child): void
    {
        throw new InvalidArgumentException(
            sprintf('%s %s cannot have child registers', class_basename($this), $this->getIdentifier())
        );
    }

    public function __debugInfo()
    {
        $return = parent::__debugInfo();

        if ($this->constructor) {
            $return['constructor'] = $this->constructor;
        }

        return $return;
    }
}
