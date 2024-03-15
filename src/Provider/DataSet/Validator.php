<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Provider\DataSet;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\Validator as LaravelValidator;

class Validator extends LaravelValidator
{
    /**
     * @var \Illuminate\Validation\Factory
     */
    protected $factory;

    /**
     * Create a new Validator instance.
     *
     * @param  \Illuminate\Contracts\Translation\Translator  $translator
     * @param  array  $data
     * @param  array  $rules
     * @param  array  $messages
     * @param  array  $customAttributes
     * @return void
     */
    public function __construct(
        Factory $factory,
        Translator $translator,
        array $data,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ) {
        $this->factory = $factory;

        $rules = RuleParser::expandWildcardRules($rules, $data);

        foreach (array_keys($rules) as $fieldName) {
            if (!isset($rules[$fieldName])) {
                continue;
            }

            $fieldRules = RuleParser::explodeRules($rules[$fieldName]);
            if (in_array(RuleParser::NESTED_DATA_SET_RULE, $fieldRules)) {
                // remove nested data set rule
                $rules[$fieldName] = array_diff($fieldRules, [RuleParser::NESTED_DATA_SET_RULE]);

                // split nested data + rules into a separate nested validator
                $rules = $this->addNestedValidator(
                    $fieldName,
                    $data,
                    $rules,
                    $messages,
                    $customAttributes
                );
            }
        }

        parent::__construct($translator, $data, $rules, $messages, $customAttributes);
    }

    /**
     * Split nested rules + data under the given fieldName into an "after" validator,
     * then return a new rule set for the main validator.
     *
     * @param  string $fieldName
     * @param  array  $data
     * @param  array  $rules
     * @param  array  $messages
     * @param  array  $customAttributes
     *
     * @return array  $rules New rule set, excluding those being validated in the nested validator
     */
    protected function addNestedValidator(
        string $fieldName,
        array $data,
        array $rules,
        array $messages,
        array $customAttributes
    ): array {
        $nestedRules = RuleParser::filterNestedItems($rules, $fieldName);

        if (is_array($nestedData = data_get($data, $fieldName))) {
            $nestedMessages =  RuleParser::filterNestedItems($messages, $fieldName);
            $nestedCustomAttributes = RuleParser::filterNestedItems($customAttributes, $fieldName);

            $nestedValidator = $this->factory->make(
                $nestedData,
                RuleParser::unprefixFieldKeys($nestedRules, $fieldName),
                RuleParser::unprefixFieldKeys($nestedMessages, $fieldName),
                RuleParser::unprefixFieldKeys($nestedCustomAttributes, $fieldName)
            );

            $this->after(function (self $parentValidator) use ($nestedValidator, $fieldName) {
                $nestedErrors = RuleParser::prefixFieldKeys(
                    $nestedValidator->errors()->toArray(),
                    $fieldName
                );

                $parentValidator->errors()->merge($nestedErrors);
            });
        }

        // remove nested rules from main validator
        return array_diff_key($rules, $nestedRules);
    }
}
