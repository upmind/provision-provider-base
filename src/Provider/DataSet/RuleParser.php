<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Provider\DataSet;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationRuleParser;
use Illuminate\Validation\Validator as LaravelValidator;

class RuleParser
{
    /**
     * Upmind nested data set marker rule. This rule indicates that the current
     * nested object attribute should be validated separately from the rest of
     * the given data, since its validation rules came from an independent data
     * set object.
     *
     * This approach avoids issues where validation rules reference sibling data
     *
     * @var string
     */
    public const NESTED_DATA_SET_RULE = 'upmind_nested_data_set';

    /**
     * @var LaravelValidator|null
     */
    protected static $validator;

    /**
     * Parse the given data set rules, expanding nested references to other data
     * sets.
     *
     * @param array<string[]> $rules Raw data set rules
     * @param string|null $parentField Name/key of the parent field, if any
     *
     * @return array<string[]> Fully expanded data set rules
     */
    public static function expand(array $rawRules, ?string $parentField = null): array
    {
        $returnRules = [];

        foreach ($rawRules as $field => $fieldRules) {
            $field = self::prefixField($field, $parentField);

            $fieldRules = self::explodeRules($fieldRules);
            foreach ($fieldRules as $rule) {
                //expand data set reference into returnRules
                if (self::isDataSet($rule)) {
                    /** @var Rules $dataSetRules */
                    $dataSetRules = $rule::rules();

                    if (!self::fieldIsArray($field)) {
                        if (!self::containsRule($fieldRules, self::NESTED_DATA_SET_RULE)) {
                            $returnRules[$field][] = self::NESTED_DATA_SET_RULE;
                        }
                    }

                    foreach ($dataSetRules->expand($field) as $expandedField => $expandedFieldRules) {
                        $returnRules[$expandedField] = $expandedFieldRules;
                    }

                    continue;
                }

                //no further rules to expand
                $returnRules[$field][] = $rule;
            }
        }

        return $returnRules;
    }

    /**
     * Convert wildcard rules away from asterisks (.*) to explicit array keys (.0),
     * based on the keys present in the given data.
     */
    public static function expandWildcardRules(array $rawRules, array $data): array
    {
        // The primary purpose of this parser is to expand any "*" rules to the all
        // of the explicit rules needed for the given data. For example the rule
        // names.* would get expanded to names.0, names.1, etc. for this data.
        $parsed = (new ValidationRuleParser($data))->explode($rawRules);

        return $parsed->rules;
    }

    /**
     * Normalizes a single attribute's rules to array format.
     *
     * @param string|array $rules
     *
     * @return array
     */
    public static function explodeRules($rules): array
    {
        return (is_string($rules)) ? explode('|', $rules) : $rules;
    }

    /**
     * Determine whether the system supports the given validation rule.
     */
    public static function isRule(string $rule): bool
    {
        [$rule, $parameters] = static::parseRule($rule);

        $validator = self::getValidator();

        if (method_exists($validator, sprintf('validate%s', Str::studly($rule)))) {
            return true; //built-in rule
        }

        if (isset($validator->extensions[strtolower($rule)])) {
            return true; //custom rule
        }

        return false;
    }

    /**
     * Determine whether the given rule is a data set reference
     */
    public static function isDataSet(string $rule): bool
    {
        return !self::isRule($rule) && is_subclass_of($rule, DataSet::class);
    }

    /**
     * Returns the rule name and its arguments as a list in format:
     * `[string $baseRule, string[] $arguments]`.
     *
     * @return array = array{0: string, 1: string[]}
     */
    public static function parseRule(string $rule): array
    {
        $arguments = [];

        if (strpos($rule, ':') !== false) {
            list($rule, $arguments) = explode(':', $rule, 2);

            $arguments = $arguments ? str_getcsv($arguments) : [];
        }

        return [$rule, $arguments];
    }

    /**
     * Get the arguments of the given rule from an array of field rules, if
     * any.
     *
     * @param string[] $fieldRules
     * @param string $rule
     *
     * @return string[]|null
     */
    public static function getRuleArguments(array $fieldRules, string $rule): ?array
    {
        foreach ($fieldRules as $fieldRule) {
            if (Str::startsWith($fieldRule, Str::finish($rule, ':'))) {
                return self::parseRule($fieldRule)[1] ?? [];
            }
        }

        return null;
    }

    /**
     * Implode the given base rule and arguments into a single rule string.
     *
     * @param string $baseRule
     * @param string[] $arguments
     */
    public static function assembleRule(string $baseRule, array $arguments): string
    {
        return $arguments
            ? sprintf('%s:%s', $baseRule, implode(',', $arguments))
            : $baseRule;
    }

    /**
     * Determine whether any of the given validation rules contain any of
     * the given check rules.
     *
     * @param string[]|array $fieldRules Validation rules for a single field or a set of rules for multiple fields
     * @param string[]|Collection<string> $checkRules Rules to check e.g., [`string`, `required_if:`]
     *
     * @return bool
     */
    public static function containsAnyRule(array $fieldRules, $checkRules): bool
    {
        foreach ($checkRules as $rule) {
            if (self::containsRule($fieldRules, $rule)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the given validation rules contain the given rule.
     * Determines the presence of a rule with or without arguments e.g., `in:`,
     * but if $checkRule contains arguments, only return true for an exact match.
     *
     * @param string[]|array $fieldRules Validation rules for a single field or a set of rules for multiple fields
     * @param string $checkRule Rule to check e.g., `string`, `required_if:`
     *
     * @return bool
     */
    public static function containsRule(array $fieldRules, string $checkRule): bool
    {
        if (Arr::isAssoc($fieldRules) || is_array(Arr::first($fieldRules))) {
            //this appears to be a set of rules for multiple fields
            foreach ($fieldRules as $rules) {
                if (self::containsRule(self::explodeRules($rules), $checkRule)) {
                    return true;
                }
            }

            return false;
        }

        $ignoreRuleArgs = true;

        if (Str::contains($checkRule, ':') && !Str::endsWith($checkRule, ':')) {
            $ignoreRuleArgs = false; //only return true for exact match since rule args have been passed
        }

        foreach ($fieldRules as $rule) {
            if ($rule === $checkRule) {
                return true; //exact match
            }

            if ($ignoreRuleArgs && is_string($rule)) {
                if (Str::startsWith($rule, Str::finish($checkRule, ':'))) {
                    return true; //match ignoring rule args
                }
            }
        }

        return false;
    }

    /**
     * Filter the given array of items to return only the items nested under
     * the given parent field by dot-notation.
     *
     * @param mixed[] $items Array of items keyed by field name, e.g., `['foo' => 'bar', 'foo.baz' => 'bam']`
     * @param string $parentField Parent field name of the desired items e.g., `foo`
     *
     * @return array Returns only items nested under the given parent field e.g., `['foo.baz' => 'bam']`
     */
    public static function filterNestedItems(array $items, string $parentField, bool $includeParent = false): array
    {
        $prefix = $includeParent
            ? [$parentField, self::prefixField('', $parentField)]
            : self::prefixField('', $parentField);

        return array_filter($items, function (string $field) use ($prefix) {
            return Str::startsWith($field, $prefix);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Determine whether the given field name denotes an array.
     */
    public static function fieldIsArray(string $field): bool
    {
        return Str::endsWith($field, '.*');
    }

    /**
     * Remove the trailing `.*` array component from the given field name.
     *
     * @param string $field Field name e.g., `foo.bar.*`
     *
     * @return string Field name without trailing array component e.g., `foo.bar`
     */
    public static function unArrayField(string $field): string
    {
        return Str::replaceLast('.*', '', $field);
    }

    /**
     * Prefix the given field name with the given parent field name.
     *
     * @param string $field E.g., `field`
     * @param string $parentField E.g., `parent_field`
     *
     * @return string E.g., `parent_field.field`
     */
    public static function prefixField(string $field, ?string $parentField): string
    {
        return $parentField ? sprintf('%s.%s', $parentField, $field) : $field;
    }

    /**
     * Remove the given parent field prefix from the given field.
     *
     * @param string $field E.g., `parent_field.field`
     * @param string $parentField E.g., `parent_field`
     *
     * @return string E.g., `field`
     */
    public static function unprefixField(string $field, ?string $parentField): string
    {
        if (!$parentField) {
            return $field;
        }

        $prefix = self::prefixField('', $parentField);

        return Str::startsWith($field, $prefix)
            ? Str::replaceFirst($prefix, '', $field)
            : $field;
    }

    /**
     * Returns the prefix of the given field, if any.
     *
     * @param string $field E.g., `parent_field.field`
     *
     * @return string|null E.g., `parent_field`
     */
    public static function getFieldPrefix(string $field): ?string
    {
        if (!Str::contains($field, '.')) {
            return null;
        }

        return Str::before($field, '.');
    }

    /**
     * Prefix the given array's keys using the given parent field name.
     *
     * @param mixed[] $items Array of items whose keys need prefixing e.g., `['foo' => 'foo', 'bar' => 'bar']`
     * @param string|null $parentField Parent field to use for prefix e.g., `baz`
     *
     * @return mixed[] E.g., `['baz.foo' => 'foo', 'baz.bar' => 'bar']`
     */
    public static function prefixFieldKeys(array $items, ?string $parentField): array
    {
        if (!$parentField) {
            return $items;
        }

        $returnItems = [];

        foreach ($items as $field => $item) {
            $returnItems[self::prefixField($field, $parentField)] = $item;
        }

        return $returnItems;
    }

    /**
     * Remove the given array's keys prefixes using the given parent field name.
     *
     * @param mixed[] $items Array of items whose keys need unprefixing e.g., `['baz.foo' => 'foo', 'baz.bar' => 'bar']`
     * @param string|null $parentField Parent field prefix to remove e.g., `baz`
     *
     * @return mixed[] E.g., `['foo' => 'foo', 'bar' => 'bar']`
     */
    public static function unprefixFieldKeys(array $items, ?string $parentField): array
    {
        if (!$parentField) {
            return $items;
        }

        $returnItems = [];

        foreach ($items as $field => $item) {
            $returnItems[self::unprefixField($field, $parentField)] = $item;
        }

        return $returnItems;
    }

    public static function getValidator(): LaravelValidator
    {
        return self::$validator = (self::$validator ?? Validator::make([], []));
    }

    public static function setValidator(LaravelValidator $validator): void
    {
        self::$validator = $validator;
    }
}
