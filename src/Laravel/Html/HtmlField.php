<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Laravel\Html;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Upmind\ProvisionBase\Laravel\Validation\ValidationHelper;
use Upmind\ProvisionBase\Provider\DataSet\RuleParser;

/**
 * Immutable value object representing a HTML field.
 *
 * @deprecated Use `\Upmind\ProvisionBase\Laravel\Html\FormField` instead.
 */
final class HtmlField
{
    /**
     * @var string[]
     */
    protected const VALID_TYPES = [
        self::TYPE_INPUT_TEXT,
        self::TYPE_INPUT_PASSWORD,
        self::TYPE_INPUT_NUMBER,
        self::TYPE_INPUT_RANGE,
        self::TYPE_CHECKBOX,
        self::TYPE_SELECT,
        self::TYPE_INPUT_RADIO,
        self::TYPE_TEXTAREA,
        self::TYPE_INPUT_DATE,
        self::TYPE_INPUT_DATETIME,
        self::TYPE_INPUT_TEL,
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
     * Data types mapped by field type.
     *
     * @var string[]
     */
    protected const DATA_TYPES = [
        self::TYPE_INPUT_TEXT => 'string',
        self::TYPE_INPUT_PASSWORD => 'string',
        self::TYPE_INPUT_NUMBER => 'numeric', //numeric type - either int or float
        self::TYPE_INPUT_RANGE => 'numeric', //numeric type - either int or float
        self::TYPE_CHECKBOX => 'bool',
        self::TYPE_SELECT => 'string',
        self::TYPE_INPUT_RADIO => 'string',
        self::TYPE_TEXTAREA => 'string',
        self::TYPE_INPUT_DATE => 'string',
        self::TYPE_INPUT_DATETIME => 'string',
        self::TYPE_INPUT_TEL => 'string',
    ];

    /**
     * Input type=text.
     *
     * @var string
     */
    public const TYPE_INPUT_TEXT = 'input_text';

    /**
     * Input type=password.
     *
     * @var string
     */
    public const TYPE_INPUT_PASSWORD = 'input_password';

    /**
     * Input type=number.
     *
     * @var string
     */
    public const TYPE_INPUT_NUMBER = 'input_number';

    /**
     * Input type=range.
     *
     * @var string
     */
    public const TYPE_INPUT_RANGE = 'input_range';

    /**
     * Input type=date.
     *
     * @var string
     */
    public const TYPE_INPUT_DATE = 'input_date';

    /**
     * Input type=datetime-local.
     *
     * @link https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/datetime-local
     *
     * @var string
     */
    public const TYPE_INPUT_DATETIME = 'input_datetime';

    /**
     * Input type=tel.
     *
     * @var string
     *
     * @link https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/tel
     */
    public const TYPE_INPUT_TEL = 'input_tel';

    /**
     * Checkbox boolean.
     *
     * @var string
     */
    public const TYPE_CHECKBOX = 'checkbox';

    /**
     * Select + options.
     *
     * @var string
     */
    public const TYPE_SELECT = 'select';

    /**
     * Radio + options.
     *
     * @var string
     */
    public const TYPE_INPUT_RADIO = 'input_radio';

    /**
     * Multi-line textarea.
     *
     * @var string
     */
    public const TYPE_TEXTAREA = 'textarea';

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var bool
     */
    protected $required;

    /**
     * @var string[]|null
     */
    protected $options;

    /**
     * @var string[]|null
     */
    protected $attributes;

    /**
     * Array of laravel validation rules.
     *
     * @var string[]
     */
    protected $validationRules;

    /**
     * @param string $name Field name
     * @param string $type Field type, e.g., HtmlField::TYPE_SELECT, HtmlField::TYPE_INPUT_PASSWORD
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
        array $validationRules = []
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
    }

    /**
     * Factory method to create a HtmlField object from an array of laravel
     * validation rules.
     *
     * @param string $name
     * @param string[] $rules
     *
     * @return static
     */
    public static function createFromValidationRules(string $name, array $rules): HtmlField
    {
        $options = null;
        $attributes = null;
        $required = false;

        foreach ($rules as $rule) {
            //normalize rule
            $rule = strtolower(trim($rule));

            //determine required attribute
            if ($rule === 'required') {
                $required = true;
                continue;
            }

            //determine min/max attribute
            if (Str::startsWith($rule, ['min:', 'max:'])) {
                [$attribute, $value] = explode(':', $rule, 2);

                $attributes[$attribute] = $value + 0; //convert string to integer|float
            }

            //determine field type from rule
            if (isset($type)) {
                continue;
            }

            if (Str::startsWith($rule, 'in:')) {
                $type = self::TYPE_SELECT;
                $options = explode(',', preg_replace('/^in:/', '', $rule));
                continue;
            }

            if (in_array($rule, ['bool', 'boolean'])) {
                $type = self::TYPE_CHECKBOX;
                continue;
            }

            if (in_array($rule, ['int', 'integer', 'numeric'])) {
                if ($rule !== 'numeric' && !isset($attributes['step'])) {
                    //set integer step
                    $attributes['step'] = 1;
                }

                $type = self::TYPE_INPUT_NUMBER;
                continue;
            }

            if ($rule === 'international_phone') {
                $type = self::TYPE_INPUT_TEL;
            }

            if (in_array($rule, ['array', 'json', 'certificate_pem'])) {
                $type = self::TYPE_TEXTAREA;
            }
        }

        $type = $type ?? self::TYPE_INPUT_TEXT;

        if (self::typeShouldBePassword($name, $type)) {
            $type = self::TYPE_INPUT_PASSWORD;
        }

        return new static($name, $type, $required, $attributes, $options, $rules);
    }

    /**
     * Whether or not this field is required.
     */
    public function required(): bool
    {
        return $this->required;
    }

    /**
     * Field name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Field type e.g., input_text.
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * @return string One of: "string", "bool", "int", "float"
     */
    public function dataType(): string
    {
        $dataType = self::DATA_TYPES[$this->type];

        if ($dataType === 'numeric') {
            //int or float
            return (is_float(Arr::get($this->attributes(), 'step')))
                ? 'float'
                : 'int';
        }

        return $dataType;
    }

    /**
     * Options for select/radio fields.
     */
    public function options(): ?array
    {
        return $this->options;
    }

    /**
     * Additional attributes e.g., min, max, step.
     */
    public function attributes(): ?array
    {
        return $this->attributes;
    }

    /**
     * Laravel validation rules for this field.
     */
    public function validationRules(): array
    {
        return $this->validationRules;
    }

    /**
     * Merge default validation rules determined from the field type/options/attributes
     * into the given array of rules.
     *
     * @param array $rules Laravel validation rules
     *
     * @return array Laravel validation rules
     */
    public function mergeDefaultValidationRules(array $rules = []): array
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
        if ($attributes = $this->attributes()) {
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

        //Add data type rule e.g., string, integer
        $dataType = ValidationHelper::shortToLongType($this->dataType());

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
        return FormField::typeShouldBePassword($name, $type);
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
                    'value' => $value,
                ];
            }
        } elseif (is_scalar(Arr::first($options))) {
            // options appear to be in format: [$value1, $value2, ...]
            $return = array_map(function ($value) {
                return [
                    'label' => ucwords(str_replace(['-', '_'], ' ', $value)),
                    'value' => $value,
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
     * Determine whether the given type is a valid/supported field type.
     *
     * @param string $type
     *
     * @return bool
     */
    public static function typeIsValid($type): bool
    {
        return in_array($type, self::VALID_TYPES);
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
            'name' => $this->name,
            'type' => $this->type,
            'required' => $this->required,
            'attributes' => $this->attributes,
            'options' => $this->options,
            'validationRules' => $this->validationRules,
        ];
    }
}
