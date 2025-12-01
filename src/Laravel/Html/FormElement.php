<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Laravel\Html;

use Illuminate\Support\Str;

/**
 * Abstract representation of a HTML form element.
 */
abstract class FormElement
{
    /**
     * @var string[]
     */
    protected const VALID_TYPES = [
        self::TYPE_GROUP,
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
     * Data types mapped by field type.
     *
     * @var string[]
     */
    protected const DATA_TYPES = [
        self::TYPE_GROUP => 'array',
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
     * A group of fields.
     *
     * @var string
     */
    public const TYPE_GROUP = 'group';

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
     * @var FormGroup|null
     */
    protected $group;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var bool
     */
    protected $required;

    /**
     * Array of laravel validation rules.
     *
     * @var string[]
     */
    protected $validationRules;

    /**
     * Element type e.g., group or input_text.
     */
    abstract public function type(): string;

    /**
     * Set the parent form group.
     */
    public function setGroup(?FormGroup $group): void
    {
        $this->group = $group;
    }

    /**
     * Parent form group.
     */
    public function group(): ?FormGroup
    {
        return $this->group;
    }

    /**
     * Element name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Element name relative to its group.
     */
    public function relativeName(): string
    {
        if (!$group = $this->group()) {
            return $this->name();
        }

        return Str::after($this->name(), $group->name() . '.');
    }

    /**
     * Scalar type of this element e.g., "string", "bool", "int", "float" or "array".
     */
    public function dataType(): string
    {
        return self::DATA_TYPES[$this->type()];
    }

    /**
     * Whether or not this element is required.
     */
    public function required(): bool
    {
        return $this->required;
    }

    /**
     * Laravel validation rules for this field or group.
     *
     * @return string[]|array<string[]>
     */
    public function validationRules(): array
    {
        return $this->validationRules ?? [];
    }

    /**
     * Determine whether the given type is a valid/supported element type.
     *
     * @param string $type
     *
     * @return bool
     */
    public static function typeIsValid($type): bool
    {
        return in_array($type, self::VALID_TYPES);
    }

    public function __debugInfo()
    {
        return [
            'name' => $this->name(),
            'relative_name' => $this->relativeName(),
            'type' => $this->type(),
            'required' => $this->required(),
            'validationRules' => $this->validationRules(),
        ];
    }
}
