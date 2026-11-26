<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Validation;

use Composer\InstalledVersions;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as FactoryContract;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Propaganistas\LaravelPhone\PhoneServiceProvider;
use Upmind\ProvisionBase\Laravel\ValidationServiceProvider;

/**
 * Runs the validation conformance cases against the Laravel validator with
 * this package's rules.
 */
class ConformanceTest extends TestCase
{
    private const NO_KOSOVO = 'v3.7 rejects country XK, because its ISO 3166 list does not include Kosovo.';

    private const NO_PHONE_FAILURE = 'v3.7 adds the error message by hand, so Validator::failed() does not report'
        . ' international_phone.';

    private const NO_NESTED_FAILURE = 'v3.7 does not report nested data set failures in Validator::failed().';

    /**
     * Cases which do not conform on v3.7, keyed by rule, then case name. The
     * value is the reason.
     *
     * @var array<string,array<string,string>>
     */
    private const KNOWN_DIFFERENCES = [
        'international_phone' => [
            'Kosovo mobile with country XK' => self::NO_KOSOVO,
            'Kosovo mobile, national format with country XK' => self::NO_KOSOVO,
            'Kosovo mobile with lower case country xk' => self::NO_KOSOVO,
            'missing country value' => self::NO_PHONE_FAILURE,
            'unknown country value' => self::NO_PHONE_FAILURE,
            'national format without country' => self::NO_PHONE_FAILURE,
            'invalid number for country' => self::NO_PHONE_FAILURE,
            'number of another country' => self::NO_PHONE_FAILURE,
            'Jersey number with country GB' => self::NO_PHONE_FAILURE,
            'national format without country value' => self::NO_PHONE_FAILURE,
            'Kosovo mobile with country RS' => self::NO_PHONE_FAILURE,
            'Serbian mobile with country XK' => self::NO_PHONE_FAILURE,
            'Canadian number with country US' => self::NO_PHONE_FAILURE,
            'Kazakhstan number with country RU' => self::NO_PHONE_FAILURE,
            'country without phone numbers' => self::NO_PHONE_FAILURE,
            'integer country value' => self::NO_PHONE_FAILURE,
            'empty country value' => self::NO_PHONE_FAILURE,
            'array value' => 'v3.7 raises a PHP warning for an array value. Laravel turns it into an ErrorException.',
        ],
        'upmind_nested_data_set' => [
            'missing nested required field' => self::NO_NESTED_FAILURE,
            'invalid nested field' => self::NO_NESTED_FAILURE,
            'empty nested data' => self::NO_NESTED_FAILURE,
            'two levels of nesting' => self::NO_NESTED_FAILURE,
        ],
    ];

    /**
     * Cases which do not conform on one Laravel minor version only, keyed by
     * version, then rule, then case name. The value is the reason.
     *
     * @var array<string,array<string,array<string,string>>>
     */
    private const LARAVEL_KNOWN_DIFFERENCES = [
        '5.8' => [
            'required_if' => [
                'boolean other field true does not match parameter 1'
                    => 'Laravel 5.8 compares loosely, so boolean true matches 1.',
            ],
        ],
    ];

    private static Factory $factory;

    public static function setUpBeforeClass(): void
    {
        $app = new Container();
        Container::setInstance($app);
        $app->singleton('validator', function (Container $app) {
            return new Factory(new Translator(new ArrayLoader(), 'en'), $app);
        });
        $app->alias('validator', FactoryContract::class);
        Facade::setFacadeApplication($app);

        // Laravel package discovery boots this provider in apps.
        (new PhoneServiceProvider($app))->boot();
        (new ValidationServiceProvider($app))->boot();

        self::$factory = $app->make('validator');
    }

    public static function tearDownAfterClass(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
    }

    public static function cases(): iterable
    {
        return ConformanceCases::cases();
    }

    /**
     * @dataProvider cases
     */
    #[DataProvider('cases')]
    public function testCase(string $rule, array $case): void
    {
        $reason = self::knownDifferences()[$rule][$case['name']] ?? null;

        if ($reason !== null) {
            $this->assertFalse(self::conforms($case), "Case conforms, so remove its known difference: {$reason}");

            return;
        }

        $validator = self::$factory->make($case['data'], $case['rules']);
        $passes = $validator->passes();

        $this->assertSame(self::sortFailed($case['failed'] ?? []), self::failedRules($validator));
        $this->assertSame($case['valid'], $passes);
    }

    public function testKnownDifferencesNameCases(): void
    {
        $files = ConformanceCases::files();
        $lists = array_merge([self::KNOWN_DIFFERENCES], array_values(self::LARAVEL_KNOWN_DIFFERENCES));

        foreach ($lists as $list) {
            foreach ($list as $rule => $reasons) {
                $names = array_column($files[$rule]['cases'] ?? [], 'name');

                foreach (array_keys($reasons) as $name) {
                    $this->assertContains($name, $names, "No {$rule} case has the name: {$name}");
                }
            }
        }
    }

    /**
     * Get the known differences of the installed Laravel version, keyed by
     * rule, then case name.
     *
     * @return array<string,array<string,string>>
     */
    private static function knownDifferences(): array
    {
        $version = (string)InstalledVersions::getVersion('illuminate/validation');
        $laravel = implode('.', array_slice(explode('.', $version), 0, 2));

        return array_merge_recursive(self::KNOWN_DIFFERENCES, self::LARAVEL_KNOWN_DIFFERENCES[$laravel] ?? []);
    }

    /**
     * Determine whether the validator gives the result of the given case. An
     * error, e.g. a PHP warning, does not conform.
     *
     * @param array<string,mixed> $case
     */
    private static function conforms(array $case): bool
    {
        try {
            $validator = self::$factory->make($case['data'], $case['rules']);

            return $validator->passes() === $case['valid']
                && self::failedRules($validator) === self::sortFailed($case['failed'] ?? []);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string,string[]> $failed
     *
     * @return array<string,string[]>
     */
    private static function sortFailed(array $failed): array
    {
        foreach ($failed as &$rules) {
            sort($rules);
        }

        ksort($failed);

        return $failed;
    }

    /**
     * Get the failed rule names of each field.
     *
     * @return array<string,string[]>
     */
    private static function failedRules(Validator $validator): array
    {
        $failed = [];

        foreach ($validator->failed() as $field => $rules) {
            $failed[$field] = array_map(function (string $rule): string {
                return Str::snake($rule);
            }, array_keys($rules));
        }

        return self::sortFailed($failed);
    }
}
