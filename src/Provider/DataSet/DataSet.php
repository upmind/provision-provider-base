<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Provider\DataSet;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use JsonSerializable;
use Upmind\ProvisionBase\Exception\InvalidDataSetException;

/**
 * DTO encapsulating a data set. If the data set is invalid according to the
 * rules returned by static::rules(), an `InvalidDataSetException` will be thrown
 * for any attempt to take data from it.
 *
 * @phpstan-consistent-constructor
 */
abstract class DataSet implements ArrayAccess, JsonSerializable, Arrayable, Jsonable
{
    private static Factory $validatorFactory;

    /**
     * Input values with nested data sets expanded.
     *
     * @var array $values
     */
    protected $values;

    /**
     * Raw input data.
     *
     * @var array $rawValues
     */
    protected $rawValues;

    /**
     * Data set rules.
     *
     * @var Rules
     */
    protected $rules;

    /**
     * @var \Illuminate\Contracts\Validation\Validator|null
     */
    protected $validator;

    /**
     * Whether the data set has been validated yet.
     *
     * @var bool
     */
    protected $isValidated = false;

    /**
     * Whether auto-validation is enabled for this data set instance.
     *
     * @var bool
     */
    protected $validationEnabled = true;

    /**
     * Returns an array of laravel validation rules for this data set.
     *
     * @link https://laravel.com/docs/5.8/validation#available-validation-rules
     *
     * @return Rules
     */
    abstract public static function rules(): Rules;

    /**
     * Instantiate the data set with the given values.
     *
     * @param mixed[] $values Raw data
     * @param bool $autoValidation Enable or disable auto-validation of this data set instance
     */
    public function __construct($values = [], bool $autoValidation = true)
    {
        $this->values = $this->rawValues = (array)$this->recursiveToArray($values); // convert to raw array(s)

        $this->rules = static::rules();
        $this->validationEnabled = $autoValidation;

        $this->fillNestedDataSets(); // cast values to data sets if appropriate
    }

    public static function getValidatorFactory(): Factory
    {
        if (self::$validatorFactory === null) {
            self::$validatorFactory = ValidatorFactory::getFacadeRoot();
        }

        return self::$validatorFactory;
    }

    public static function setValidatorFactory(Factory $validatorFactory): void
    {
        self::$validatorFactory = $validatorFactory;
    }

    /**
     * Set/update a value on the data set, with automatic casting for nested data
     * sets.
     *
     * @param string $key Data set key
     * @param mixed $value Data set value
     */
    protected function setValue(string $key, $value): void
    {
        $value = $this->recursiveToArray($value); // convert to raw array(s)

        $this->rawValues[$key] = $value;
        $this->values[$key] = $this->castValue($key, $value); //cast to data set if appropriate

        $this->validator = null;
        $this->isValidated = false;
    }

    /**
     * Instantiate the data set with the given values.
     *
     * @param mixed[] $values Raw data
     * @param bool $validationEnabled Enable or disable validation of this data set instance
     *
     * @return static
     */
    public static function create($values = [], bool $validationEnabled = true)
    {
        return new static($values, $validationEnabled);
    }

    /**
     * Get a value from the data set by key.
     *
     * @param string $key Key of the desired value
     * @param mixed $default Default value to return if the given key does not exist
     *
     * @return mixed|null
     */
    public function get($key, $default = null)
    {
        $this->autoValidate();

        return $this->has($key)
            ? $this->values[$key]
            : value($default);
    }

    /**
     * Determine whether the data set contains the given value by key.
     *
     * @param string $key Key of the value to be checked
     *
     * @return bool
     */
    public function has($key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * Get all values from the data set, with nested data sets expanded.
     *
     * @return mixed[]
     */
    public function all(): array
    {
        $this->autoValidate();

        return $this->values;
    }

    /**
     * Get the raw values used to instantiate the data set.
     *
     * @return mixed[]
     */
    public function raw(): array
    {
        $this->autoValidate();

        return $this->rawValues;
    }

    /**
     * Get or toggle "auto-validation". If auto-validation is enabled, the DataSet
     * will validate itself the first time values are accessed.
     *
     * @param bool|null $enabled Pass bool to set, or null to get
     *
     * @return bool Whether or not the DataSet will auto-validate
     */
    public function autoValidation(?bool $enabled = null): bool
    {
        if (isset($enabled)) {
            $this->validationEnabled = $enabled;
        }

        return $this->validationEnabled;
    }

    /**
     * Get or set this instance's "auto-validation" toggle.
     *
     * @deprecated Use `DataSet::autoValidation()` instead
     *
     * @param bool|null $enabled Pass true or false to enable/disable, or null to return current setting
     *
     * @return bool Whether auto-validation is enabled or not
     */
    public function validationEnabled(?bool $enabled = null): bool
    {
        return $this->autoValidation($enabled);
    }

    /**
     * Validate the data set against its validation rules.
     *
     * @throws InvalidDataSetException If the data set is invalid
     */
    public function validate(): void
    {
        try {
            $this->validator()->validate();
            $this->isValidated = true;
        } catch (ValidationException $e) {
            $this->isValidated = false;
            throw new InvalidDataSetException($e);
        }
    }

    /**
     * Determine whether this data set has been validated.
     */
    public function isValidated(): bool
    {
        return $this->isValidated;
    }

    /**
     * Validate the data set if it has not yet been validated.
     *
     * @throws InvalidDataSetException If the data set is invalid
     */
    public function validateIfNotYetValidated(): void
    {
        if (!$this->isValidated()) {
            $this->validate();
        }
    }

    /**
     * Get an array of validation errors for this instance's data.
     *
     * @return array<string[]>
     */
    public function errors(): array
    {
        try {
            $this->validate();

            return [];
        } catch (InvalidDataSetException $e) {
            return $e->errors();
        }
    }

    /**
     * Validate the data set if auto-validation is enabled and the data has not yet been validated.
     *
     * @throws InvalidDataSetException If the data set is invalid
     */
    protected function autoValidate(): void
    {
        if ($this->autoValidation()) {
            $this->validateIfNotYetValidated();
        }
    }

    /**
     * Get the validator for this data set.
     */
    protected function validator(): Validator
    {
        if (!isset($this->validator)) {
            $this->validator = $this->makeValidator($this->rawValues, $this->rules);
        }

        return $this->validator;
    }

    /**
     * Instantiate and return a new validator for the given data and validation rules.
     *
     * @param array $data Raw input data
     * @param Rules $rules Data set rules
     */
    protected function makeValidator($data, Rules $rules): Validator
    {
        return self::getValidatorFactory()->make($data, $rules->expand());
    }

    /**
     * Replace nested data with nested data sets, according to this data set's
     * rules.
     */
    protected function fillNestedDataSets(): void
    {
        foreach ($this->rules->raw() as $field => $rules) {
            foreach (RuleParser::explodeRules($rules) as $rule) {
                if (RuleParser::isDataSet($rule)) {
                    if (is_array($data = data_get($this->values, $field))) {
                        if (RuleParser::fieldIsArray($field)) {
                            $field = RuleParser::unArrayField($field);

                            $data = array_map(function ($data) use ($rule) {
                                return $this->castToDataSet($data, $rule);
                            }, $data);
                        } else {
                            $data = $this->castToDataSet($data, $rule);
                        }

                        data_set($this->values, $field, $data);
                    }

                    continue 2;
                }
            }
        }
    }

    /**
     * Casts the given value to a data set according to this data set's rules.
     *
     * @param string $key Value key
     * @param mixed $value Value data
     *
     * @return mixed|DataSet $value The given value, cast to a DataSet if appropriate
     */
    protected function castValue(string $key, $value)
    {
        $keys = [$key, sprintf('%s.*', $key)];

        foreach ($keys as $ruleKey) {
            foreach (RuleParser::explodeRules($this->rules->raw($ruleKey)) as $rule) {
                if (RuleParser::isDataSet($rule) && is_array($value) && !empty($value)) {
                    if (RuleParser::fieldIsArray($ruleKey)) {
                        return array_map(function ($value) use ($rule) {
                            return $this->castToDataSet($value, $rule);
                        }, $value);
                    }

                    return $this->castToDataSet($value, $rule);
                }
            }
        }

        return $value;
    }

    /**
     * Cast the given value to the given DataSet.
     *
     * @param array|DataSet $data Raw data
     * @param string $dataSetClass DataSet class
     *
     * @return DataSet
     */
    protected function castToDataSet($data, string $dataSetClass): DataSet
    {
        return $dataSetClass::create($data, false);
    }

    /**
     * Determine whether the data set contains the given value using array access
     * syntax.
     *
     * @param string $offset
     *
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    /**
     * Get a value from the data set using array access syntax.
     */
    public function offsetGet($offset): mixed
    {
        return $this->get($offset, function () use ($offset) {
            if (!array_key_exists($offset, $this->rules->raw())) {
                trigger_error(sprintf('Undefined data set index: %s[%s]', get_class($this), $offset), E_USER_NOTICE);
            }

            return null;
        });
    }

    /**
     * Don't allow mutations to the data set from outside.
     */
    public function offsetSet($offset, $value): void
    {
        //
    }

    /**
     * Don't allow mutations to the data set from outside.
     */
    public function offsetUnset($offset): void
    {
        //
    }

    /**
     * Determine whether the data set contains the given value using object
     * property access syntax.
     *
     * @param string $key
     *
     * @return bool
     */
    public function __isset($key): bool
    {
        return $this->has($key);
    }

    /**
     * Get a value from the data set using object property access syntax.
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key, function () use ($key) {
            if (!array_key_exists($key, $this->rules->raw())) {
                trigger_error(sprintf('Undefined data set property: %s::$%s', get_class($this), $key), E_USER_NOTICE);
            }

            return null;
        });
    }

    /**
     * Don't allow mutations to the data set from outside.
     */
    public function __set($key, $value): void
    {
        return;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->raw();
    }

    /**
     * Convert the given value to plain array(s) recursively.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    protected function recursiveToArray($value)
    {
        if ($value instanceof DataSet) {
            $value = clone $value; // dont interfere with references to this object outside this class
            $value->autoValidation(false);
        }

        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->recursiveToArray($v);
            }

            return $value;
        }

        if ($value instanceof Arrayable) {
            return $this->recursiveToArray($value->toArray());
        }

        return $value;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options | JSON_THROW_ON_ERROR);
    }

    public function __sleep()
    {
        $this->validator = null;

        return array_keys(get_object_vars($this));
    }

    public function __debugInfo()
    {
        return $this->values;
    }
}
