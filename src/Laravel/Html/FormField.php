<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Laravel\Html;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Upmind\ProvisionBase\Laravel\Validation\ValidationHelper;
use Upmind\ProvisionBase\Provider\DataSet\RuleParser;

/**
 * Representation of a HTML form field.
 */
class FormField extends FormElement
{
    /**
     * Element types which may be used for passwords.
     *
     * @var string[]
     */
    protected const PASSWORDABLE_TYPES = [
        self::TYPE_INPUT_TEXT,
        self::TYPE_INPUT_PASSWORD,
    ];

    /**
     * @var string[]
     */
    protected const NUMERIC_TYPES = [
        self::TYPE_INPUT_NUMBER,
        self::TYPE_INPUT_RANGE,
    ];

    /**
     * @var string[]
     */
    protected const OPTIONS_TYPES = [
        self::TYPE_SELECT,
        self::TYPE_INPUT_RADIO,
    ];

    /**
     * Regexes to match a "password-y" name.
     *
     * @var string[]
     */
    protected const PASSWORD_NAME_REGEXES = [
        '/password/i',
        '/secret/i',
        '/^key$/i',
        '/(?:api|secret|access)[_\-]?(key|token)/i',
    ];

    /**
     * @var string
     */
    protected $type;

    /**
     * @var OptionData[]|null
     */
    protected $options;

    /**
     * @var string[]|null
     */
    protected $attributes;

    /**
     * @param string $name Field name
     * @param string $type Field type, e.g., FormField::TYPE_SELECT, FormField::TYPE_INPUT_PASSWORD
     * @param bool $required Whether or not the field is required
     * @param string[]|null $attributes Key=>Value attribute pairs e.g., ['min' => 3, 'max' => 10]
     * @param string[]|null $options Value=>Label option pairs for select/radio fields e.g., ['4wd' => '4-Wheel Drive']
     * @param string[] $validationRules Laravel validation rules
     */
    public function __construct(
        string $name,
        string $type = self::TYPE_INPUT_TEXT,
        bool $required = false,
        ?array $attributes = null,
        ?array $options = null,
        array $validationRules = [],
        ?FormGroup $group = null
    ) {
        if (!self::typeIsValid($type)) {
            throw new InvalidArgumentException(sprintf('Invalid field type: %s', $type));
        }

        if (!self::typeHasOptions($type)) {
            $options = null;
        } elseif (empty($options)) {
            throw new InvalidArgumentException(sprintf('Field type %s must have options', $type));
        }

        $this->name = $name;
        $this->type = $type;
        $this->required = $required;
        $this->attributes = $attributes;
        $this->options = self::normalizeOptions($options);
        $this->validationRules = $this->mergeDefaultValidationRules($validationRules);
        $this->group = $group;
    }

    /**
     * Field type e.g., input_text.
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Options for select/radio fields.
     *
     * @return OptionData[]|null
     */
    public function options(): ?array
    {
        return $this->options;
    }

    /**
     * Additional field attributes e.g., min, max, step.
     *
     * @return string[]|null
     */
    public function attributes(): ?array
    {
        return $this->attributes;
    }

    /**
     * @return string One of: "string", "bool", "int", "float" or "array"
     */
    public function dataType(): string
    {
        $dataType = parent::dataType();

        if ($dataType === 'numeric') {
            return $this->determineNumericDataType($this->validationRules(), $this->attributes());
        }

        return $dataType;
    }

    /**
     * Determine whether int or float should be used for the numeric data type.
     */
    protected function determineNumericDataType(array $validationRules, ?array $attributes): string
    {
        //int or float
        if (RuleParser::containsAnyRule($validationRules, ['integer', 'int'])) {
            return 'int';
        }

        $step = Arr::get($attributes ?? [], 'step')
            ?: Arr::first(RuleParser::getRuleArguments($validationRules, 'step:') ?? []);

        if ($step && !Str::contains((string)$step, '.')) {
            return 'int';
        }

        return 'float';
    }

    /**
     * Merge default validation rules determined from the field type/options/attributes
     * into the given array of rules.
     *
     * @param array $rules Laravel validation rules
     * @param array $attributes Field attributes
     *
     * @return array Laravel validation rules
     */
    public function mergeDefaultValidationRules(array $rules = [], array $attributes = []): array
    {
        //Add in: rule for fields with options
        if ($options = $this->options()) {
            if (!RuleParser::containsRule($rules, 'in')) {
                $optionValues = array_column($options, 'value');
                if (!$this->required()) {
                    array_unshift($optionValues, ''); //add empty option
                }

                array_unshift($rules, implode(':', ['in', implode(',', $optionValues)]));
            }
        }

        //Add arbitrary attribute rules such as min: max: step:
        if ($attributes) {
            foreach ($attributes as $attribute => $value) {
                if (in_array(strtolower($attribute), ['minlength', 'maxlength'])) {
                    $attribute = Str::replaceLast('length', '', $attribute);
                }

                if (!RuleParser::containsRule($rules, $attribute)) {
                    if (ValidationHelper::ruleExists($attribute)) {
                        array_unshift($rules, implode(':', [$attribute, $value]));
                    }
                }
            }
        }

        if ($this->type() === static::TYPE_INPUT_DATE) {
            if (!RuleParser::containsRule($rules, 'date_format')) {
                array_unshift($rules, 'date_format:Y-m-d');
            }
        }

        if ($this->type() === static::TYPE_INPUT_DATETIME) {
            if (!RuleParser::containsRule($rules, 'date_format')) {
                array_unshift($rules, 'date_format:Y-m-d H:i:s');
            }
        }

        if ($this->type() === static::TYPE_INPUT_NUMBER) {
            $dataType = $this->determineNumericDataType($rules, $attributes);
        }

        //Add data type rule e.g., string, integer
        $dataType = ValidationHelper::shortToLongType($dataType ?? $this->dataType());

        if ($dataType === 'float') {
            $dataType = 'numeric';
        }

        if (!RuleParser::containsRule($rules, $dataType)) {
            array_unshift($rules, $dataType);
        }

        if ($this->required() && !RuleParser::containsRule($rules, 'required')) {
            array_unshift($rules, 'required');
        }

        return $rules;
    }

    /**
     * Determine whether the given field name + type should be a password field.
     *
     * @param string $name
     * @param string $type
     *
     * @return bool
     */
    public static function typeShouldBePassword(string $name, $type): bool
    {
        if (!in_array($type, self::PASSWORDABLE_TYPES)) {
            return false;
        }

        foreach (self::PASSWORD_NAME_REGEXES as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize the given options to return labels keyed by values.
     *
     * @param string[]|null $options
     *
     * @return OptionData[]|null
     */
    public static function normalizeOptions(?array $options): ?array
    {
        if (empty($options)) {
            return $options;
        }

        $return = [];

        if (Arr::isAssoc($options)) {
            // options appear to be in format: [$value => $label, ...]
            foreach ($options as $value => $label) {
                $return[] = [
                    'label' => $label,
                    'value' => (string)$value,
                ];
            }
        } elseif (is_scalar(Arr::first($options))) {
            // options appear to be in format: [$value1, $value2, ...]
            $return = array_map(function ($value) {
                return [
                    'label' => ucwords(str_replace(['-', '_'], ' ', $value)),
                    'value' => (string)$value,
                ];
            }, $options);
        } else {
            // options appear to be in correct format
            $return = $options;
        }

        // wrap in dto
        return array_map(function ($option) {
            return $option instanceof OptionData
                ? $option
                : new OptionData($option);
        }, $return);
    }

    /**
     * Determine whether the given field type has string values.
     *
     * @param string $type
     *
     * @return bool
     */
    public static function typeIsString($type): bool
    {
        return in_array($type, array_keys(self::DATA_TYPES, 'string'));
    }

    /**
     * Determine whether the given field type has numeric values.
     *
     * @param string $type
     *
     * @return bool
     */
    public static function typeIsNumeric($type): bool
    {
        return in_array($type, array_keys(self::DATA_TYPES, 'numeric'));
    }

    /**
     * Determine whether the given field type needs an array of options.
     *
     * @param string
     *
     * @return bool
     */
    public static function typeHasOptions($type): bool
    {
        return in_array($type, self::OPTIONS_TYPES);
    }

    /**
     * Returns a renderable (scalar) mutation of the given value for the given field type.
     *
     * @param string|null $type
     * @param mixed $value
     *
     * @return mixed
     */
    public static function getRenderableValue(?string $type, $value)
    {
        switch ($type) {
            case static::TYPE_TEXTAREA:
                if (is_iterable($value)) {
                    $return = '';
                    foreach ($value as $k => $v) {
                        $v = json_encode($v, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
                        $return .= sprintf("%s: %s\n", $k, $v);
                    }
                    return $return;
                }
                // fall-through
            default:
                if (static::typeIsString($type)) {
                    if (is_object($value) || is_array($value)) {
                        return json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE);
                    }

                    return strval($value);
                }

                return $value;
        }
    }

    public function __debugInfo()
    {
        return [
            'name' => $this->name(),
            'relative_name' => $this->relativeName(),
            'type' => $this->type(),
            'required' => $this->required(),
            'attributes' => $this->attributes(),
            'options' => $this->options(),
            'validationRules' => $this->validationRules(),
        ];
    }
}
