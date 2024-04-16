<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Laravel\Html;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Upmind\ProvisionBase\Provider\DataSet\RuleParser;

/**
 * Factory class for generating HTML Forms from Laravel validation rules.
 */
class FormFactory
{
    /**
     * Create a html form from the given validation rules.
     *
     * @param array<string[]> $rules Assoc array of validation rules for multiple fields
     */
    public function create(array $rules): Form
    {
        return new Form(
            $this->createGroup('', $this->normaliseRules($rules))->elements()
        );
    }

    /**
     * Create a group of field elements from from the given validation rules.
     *
     * @param string $group Group name
     * @param array<string[]> $rules Assoc array of validation rules for multiple fields
     * @param bool $required Whether or not the group is required
     *
     * @return FormGroup
     */
    public function createGroup(string $group, array $rules, bool $required = false): FormGroup
    {
        $elements = [];

        foreach ($rules as $field => $fieldRules) {
            if ($field === $group) {
                continue;
            }

            $relativeField = RuleParser::unprefixField($field, $group);
            $relativeGroup = RuleParser::getFieldPrefix($relativeField);

            if ($relativeGroup && isset($elements[$relativeGroup])) {
                continue;
            }

            if ($this->shouldBeGroup($fieldRules) || RuleParser::filterNestedItems($rules, $field)) {
                $groupRules = RuleParser::filterNestedItems($rules, $field, true);

                $elements[$relativeField] = $this->createGroup(
                    $field,
                    $groupRules,
                    RuleParser::containsRule($groupRules[$field], 'required')
                );

                continue;
            }

            $elements[$relativeField] = $this->createField($field, $fieldRules);
        }

        return new FormGroup($group, $required, $elements, $rules);
    }

    /**
     * Factory method to create a FormField object from an array of laravel
     * validation rules for a single field.
     *
     * @param string $name
     * @param string[] $rules Flat array of rules for a single field.
     *
     * @return FormField
     */
    public function createField(string $name, array $rules): FormField
    {
        if (Arr::isAssoc($rules)) {
            throw new InvalidArgumentException('Validation rules appear to be for multiple fields');
        }

        $options = null;
        $attributes = null;
        $required = false;

        foreach ($rules as $rule) {
            //normalize rule
            $rule = trim($rule);

            //determine required attribute
            if ($rule === 'required') {
                $required = true;
                continue;
            }

            //determine min/max attribute
            if (Str::startsWith($rule, ['min:', 'max:'])) {
                [$attribute, $value] = explode(':', $rule, 2);

                //convert string to integer|float, depending if it contains a dot.
                $attributes[$attribute] = str_contains($value, '.') ? (float) $value : (int) $value;
            }

            //determine field type from rule
            if (isset($type)) {
                continue;
            }

            if (Str::startsWith($rule, 'in:')) {
                $type = FormField::TYPE_SELECT;
                $options = explode(',', preg_replace('/^in:/', '', $rule));
                continue;
            }

            if (in_array($rule, ['bool', 'boolean'])) {
                $type = FormField::TYPE_CHECKBOX;
                continue;
            }

            if (in_array($rule, ['int', 'integer', 'numeric'])) {
                if ($step = Arr::first(RuleParser::getRuleArguments($rules, 'step') ?? [])) {
                    $attributes['step'] = $step;
                }

                if ($rule !== 'numeric' && !isset($attributes['step'])) {
                    //set integer step
                    $attributes['step'] = 1;
                }

                $type = FormField::TYPE_INPUT_NUMBER;
                continue;
            }

            if ($rule === 'international_phone') {
                $type = FormField::TYPE_INPUT_TEL;
            }

            if (in_array($rule, ['array', 'json', 'certificate_pem'])) {
                $type = FormField::TYPE_TEXTAREA;
            }
        }

        $type = $type ?? FormField::TYPE_INPUT_TEXT;

        if (FormField::typeShouldBePassword($name, $type)) {
            $type = FormField::TYPE_INPUT_PASSWORD;
        }

        return new FormField($name, $type, $required, $attributes, $options, $rules);
    }

    protected function shouldBeGroup(array $fieldRules): bool
    {
        return RuleParser::containsRule($fieldRules, RuleParser::NESTED_DATA_SET_RULE)
            || RuleParser::containsRule($fieldRules, 'array');
    }

    /**
     * Normalise a set of fields' rules so nested arrays are grouped together and
     * missing parent fields are added.
     *
     * @param array<string[]> $rules
     *
     * @return array<string[]>
     */
    public function normaliseRules(array $rules, ?string $parent = null): array
    {
        $return = [];

        foreach ($rules as $field => $fieldRules) {
            if (isset($return[$field])) {
                continue;
            }

            $relativeField = RuleParser::unprefixField($field, $parent);
            if ($relativeGroup = RuleParser::getFieldPrefix($relativeField)) {
                $group = RuleParser::prefixField($relativeGroup, $parent);

                if (!isset($return[$group])) {
                    $return[$group] = $rules[$group] ?? ['array'];
                }

                $return = array_merge(
                    $return,
                    $this->normaliseRules(RuleParser::filterNestedItems($rules, $group), $group)
                );

                continue;
            }

            $return[$field] = $fieldRules;
        }

        return array_map(function ($rules) {
            while (false !== ($pos = array_search(RuleParser::NESTED_DATA_SET_RULE, $rules))) {
                $rules[$pos] = 'array';
            }

            return array_values(array_unique($rules));
        }, $return);
    }
}
