<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Laravel\Validation;

use Illuminate\Support\Str;
use Upmind\ProvisionBase\Exception\UnsupportedValidationRule;
use Upmind\ProvisionBase\Provider\DataSet\RuleParser;

/**
 * Validation rules which data sets can use.
 *
 * Each supported rule has conformance cases in tests/conformance/validation.
 * Other validator implementations must pass the same cases.
 */
final class SupportedRules
{
    /**
     * Supported rule names.
     *
     * @var string[]
     */
    public const RULES = [
        // Laravel rules
        'accepted',
        'alpha_dash',
        'alpha_num',
        'array',
        'boolean',
        'date',
        'date_format',
        'email',
        'ends_with',
        'filled',
        'in',
        'integer',
        'ip',
        'max',
        'min',
        'nullable',
        'numeric',
        'present',
        'regex',
        'required',
        'required_if',
        'required_without',
        'required_without_all',
        'size',
        'starts_with',
        'string',
        'url',

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
     * Rules which data sets can never use, with the reason.
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
     * Rule parameters which data sets can never use, with the reason.
     *
     * @var array<string,array<string,string>>
     */
    public const BLOCKED_PARAMETERS = [
        'email' => [
            'dns' => 'does a DNS lookup',
        ],
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

        [, $parameters] = RuleParser::parseRule(trim($rule));

        foreach (self::BLOCKED_PARAMETERS[$name] ?? [] as $parameter => $reason) {
            if (in_array($parameter, array_map('strtolower', array_map('trim', $parameters)), true)) {
                throw UnsupportedValidationRule::forBlockedParameter($name, $parameter, $reason);
            }
        }

        if (!in_array($name, self::RULES, true)) {
            throw UnsupportedValidationRule::forUnsupportedRule($name);
        }
    }
}
