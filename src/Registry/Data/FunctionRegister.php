<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Registry\Data;

/**
 * Register for a function.
 */
class FunctionRegister implements RegisterInterface
{
    /**
     * Function name.
     *
     * @var string
     */
    protected $name;

    /**
     * Category or Provider parent register.
     *
     * @var CategoryRegister|ProviderRegister
     */
    protected $parent;

    /**
     * Function parameter DataSet.
     *
     * @var DataSetRegister
     */
    protected $parameter;

    /**
     * Function return DataSet.
     *
     * @var DataSetRegister
     */
    protected $return;

    /**
     * @param string $name Function name
     * @param ClassRegister $parent Parent class
     * @param string|null $parameterDataSetClass DataSet class name for the function parameters
     * @param string|null $returnDataSetClass DataSet class name for the function return data
     */
    public function __construct(
        string $name,
        ?string $parameterDataSetClass,
        ?string $returnDataSetClass,
        ClassRegister $parent
    ) {
        $this->name = $name;
        $this->parent = $parent;
        $this->parameter = new DataSetRegister(DataSetRegister::TYPE_PARAMETER, $parameterDataSetClass, $this);
        $this->return = new DataSetRegister(DataSetRegister::TYPE_RETURN, $returnDataSetClass, $this);
    }

    /**
     * Enumerate parameter and return data.
     */
    public function enumerateData(): void
    {
        $this->getParameter()->enumerateData();
        $this->getReturn()->enumerateData();
    }

    /**
     * Determine whether the given function register instance or name is this register.
     */
    public function is($register): bool
    {
        if (is_string($register)) {
            return $register === $this->getName();
        }

        return $register === $this;
    }

    /**
     * Determine whether this register is for a provider constructor.
     */
    public function isConstructor(): bool
    {
        return $this->parent instanceof ProviderRegister
            && $this->name === '__construct';
    }

    /**
     * Get the function name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the parent class.
     */
    public function getParent(): ClassRegister
    {
        return $this->parent;
    }

    /**
     * Get the category register of this function.
     */
    public function getCategory(): CategoryRegister
    {
        if ($this->parent instanceof CategoryRegister) {
            return $this->parent;
        }

        return $this->parent->getCategory();
    }

    /**
     * Get the function parameter data set.
     */
    public function getParameter(): DataSetRegister
    {
        return $this->parameter;
    }

    /**
     * Get the function return data set.
     */
    public function getReturn(): DataSetRegister
    {
        return $this->return;
    }

    public function __toString()
    {
        return $this->name;
    }

    public function __debugInfo()
    {
        return [
            'name' => $this->name,
            'parameter' => $this->parameter,
            'return' => $this->return,
        ];
    }
}
