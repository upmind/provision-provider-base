<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Registry\Data;

use InvalidArgumentException;
use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\EmptyData;
use Upmind\ProvisionBase\Provider\DataSet\ResultData;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * Register for a DataSet.
 */
class DataSetRegister implements RegisterInterface
{
    /**
     * DataSet is a parameter type.
     *
     * @var string
     */
    public const TYPE_PARAMETER = 'parameter';

    /**
     * DataSet is a return type.
     *
     * @var string
     */
    public const TYPE_RETURN = 'return';

    /**
     * One of: `self::TYPE_PARAMETER`, `self::TYPE_RETURN`.
     *
     * @var string
     */
    protected $type;

    /**
     * DataSet class name.
     *
     * @var string
     */
    protected $class;

    /**
     * DataSet rules.
     *
     * @var Rules
     */
    protected $rules = null;

    /**
     * Parent function.
     *
     * @var FunctionRegister
     */
    protected $function;

    /**
     * @param string $type One of: `self::TYPE_PARAMETER`, `self::TYPE_RETURN`
     * @param string|null $class DataSet class, if any
     * @param FunctionRegister $function Parent function
     */
    final public function __construct(string $type, ?string $class, FunctionRegister $function)
    {
        $this->type = $type;
        $this->class = $class ?: $this->getDefaultClass($type, $function);
        $this->function = $function;

        $this->checkType();
        $this->checkClass();
    }

    /**
     * Enumerate data set rules.
     */
    public function enumerateData(): void
    {
        $this->getRules()->expand();
    }

    /**
     * Determine whether the given data set register instance or class is this register.
     */
    public function is($dataSet): bool
    {
        if (is_string($dataSet)) {
            return $dataSet === $this->getClass();
        }

        return $dataSet === $this;
    }

    /**
     * Determine whether this is the function parameter.
     */
    public function isParameter(): bool
    {
        return $this->getType() === self::TYPE_PARAMETER;
    }

    /**
     * Determine whether this is the function return.
     */
    public function isReturn(): bool
    {
        return $this->getType() === self::TYPE_RETURN;
    }

    /**
     * Return the type, one of: `self::TYPE_PARAMETER`, `self::TYPE_RETURN`.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Return the DataSet class name.
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Return the DataSet Rules.
     */
    public function getRules(): Rules
    {
        if (!isset($this->rules)) {
            $this->rules = $this->class::rules();
        }

        return $this->rules;
    }

    /**
     * Return the parent function register.
     */
    public function getFunction(): FunctionRegister
    {
        return $this->function;
    }

    /**
     * Get the default class for this data set register.
     *
     * @return string ResultData or EmptyData classes, depending on type/function.
     */
    protected function getDefaultClass(string $type, FunctionRegister $function): string
    {
        if ($type === self::TYPE_RETURN && !$function->isConstructor()) {
            return ResultData::class;
        }

        return EmptyData::class;
    }

    /**
     * Check the data set register type.
     */
    protected function checkType(): void
    {
        if (!in_array($this->getType(), [self::TYPE_PARAMETER, self::TYPE_RETURN])) {
            throw new InvalidArgumentException(sprintf('%s type is invalid', class_basename($this)));
        }
    }

    /**
     * Check the data set class.
     */
    protected function checkClass(): void
    {
        if (!is_subclass_of($this->getClass(), DataSet::class)) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s class must be a subclass of %s',
                    class_basename($this->getClass()),
                    class_basename(DataSet::class)
                )
            );
        }
    }

    public function __toString()
    {
        return class_basename($this->class);
    }

    public function __debugInfo()
    {
        $return = [
            'type' => $this->type,
            'class' => $this->class
        ];

        if (isset($this->rules)) {
            $return['rules'] = $this->rules;
        }

        return $return;
    }
}
