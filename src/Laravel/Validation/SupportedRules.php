<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Laravel\Validation;

use Illuminate\Support\Str;
use Upmind\ProvisionBase\Exception\UnsupportedValidationRule;
use Upmind\ProvisionBase\Provider\DataSet\RuleParser;

/**
 * Validation rules which data sets can use.
 *
 * The package upmind/provision-validation-conformance lists the same rules in
 * rules.json and has conformance cases for each rule. Other validator
 * implementations must pass the same cases.
 */
final class SupportedRules
{
    /**
     * Supported rule names.
     *
     * @var string[]
     */
    public const RULES = [
        // Laravel rules which need only the input data
        'accepted',
        'accepted_if',
        'after',
        'after_or_equal',
        'alpha',
        'alpha_dash',
        'alpha_num',
        'array',
        'array_keys',
        'ascii',
        'bail',
        'base64',
        'before',
        'before_or_equal',
        'between',
        'boolean',
        'confirmed',
        'contains',
        'date',
        'date_equals',
        'date_format',
        'decimal',
        'declined',
        'declined_if',
        'different',
        'digits',
        'digits_between',
        'distinct',
        'doesnt_contain',
        'doesnt_end_with',
        'doesnt_start_with',
        'email',
        'encoding',
        'ends_with',
        'exclude',
        'exclude_if',
        'exclude_unless',
        'exclude_with',
        'exclude_without',
        'filled',
        'gt',
        'gte',
        'hex_color',
        'in',
        'in_array',
        'in_array_keys',
        'integer',
        'ip',
        'ipv4',
        'ipv6',
        'json',
        'list',
        'lowercase',
        'lt',
        'lte',
        'mac_address',
        'max',
        'max_digits',
        'min',
        'min_digits',
        'missing',
        'missing_if',
        'missing_unless',
        'missing_with',
        'missing_with_all',
        'multiple_of',
        'not_in',
        'not_regex',
        'nullable',
        'numeric',
        'present',
        'present_if',
        'present_unless',
        'present_with',
        'present_with_all',
        'prohibited',
        'prohibited_if',
        'prohibited_if_accepted',
        'prohibited_if_declined',
        'prohibited_unless',
        'prohibits',
        'regex',
        'required',
        'required_array_keys',
        'required_if',
        'required_if_accepted',
        'required_if_declined',
        'required_unless',
        'required_with',
        'required_with_all',
        'required_without',
        'required_without_all',
        'same',
        'size',
        'sometimes',
        'starts_with',
        'string',
        'timezone',
        'ulid',
        'uppercase',
        'url',
        'uuid',

        // Marks a nested data set. RuleParser::expand() adds it.
        'upmind_nested_data_set',

        // Custom rules of this package
        'alpha_dash_dot',
        'alpha_score',
        'certificate_pem',
        'contains_lower',
        'contains_number',
        'contains_upper',
        'country_code',
        'domain_name',
        'international_phone',
        'step',
    ];

    /**
     * Rule aliases which Laravel resolves to another rule.
     *
     * @var array<string,string>
     */
    public const ALIASES = [
        'bool' => 'boolean',
        'int' => 'integer',
    ];

    /**
     * Rules which data sets can never use, with the reason. A rule which is
     * not in RULES is not supported. This list gives a clear error message.
     *
     * @var array<string,string>
     */
    public const BLOCKED = [
        'exists' => 'queries a database',
        'unique' => 'queries a database',
        'active_url' => 'does a DNS lookup',
        'password' => 'reads the authenticated user',
        'current_password' => 'reads the authenticated user',
        'file' => 'inspects uploaded files',
        'image' => 'inspects uploaded files',
        'mimes' => 'inspects uploaded files',
        'mimetypes' => 'inspects uploaded files',
        'extensions' => 'inspects uploaded files',
        'dimensions' => 'inspects uploaded files',
    ];

    /**
     * Get the canonical name of the given rule, e.g. `domain_name` for
     * `domain-name` and `boolean` for `bool`.
     */
    public static function name(string $rule): string
    {
        [$name] = RuleParser::parseRule(trim($rule));
        $name = Str::snake(Str::studly($name));

        return self::ALIASES[$name] ?? $name;
    }

    public static function isSupported(string $rule): bool
    {
        try {
            self::assertSupported($rule);
        } catch (UnsupportedValidationRule $e) {
            return false;
        }

        return true;
    }

    /**
     * @throws UnsupportedValidationRule If data sets cannot use the given rule
     */
    public static function assertSupported(string $rule): void
    {
        $name = self::name($rule);

        if (isset(self::BLOCKED[$name])) {
            throw UnsupportedValidationRule::forBlockedRule($name, self::BLOCKED[$name]);
        }

        if (!in_array($name, self::RULES, true)) {
            throw UnsupportedValidationRule::forUnsupportedRule($name);
        }
    }
}
