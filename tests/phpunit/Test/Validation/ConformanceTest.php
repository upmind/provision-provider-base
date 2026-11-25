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
    /**
     * Cases where an older Laravel version does not conform, keyed by
     * Laravel minor version, then case label. The value is the reason.
     *
     * @var array<string,array<string,string>>
     */
    private const KNOWN_DIFFERENCES = [
        '5.8' => [
            'required_if #7: boolean other field true does not match parameter 1'
                => 'Laravel 5.8 compares loosely, so boolean true matches 1',
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
        $label = (string)$this->dataName();
        $laravel = implode('.', array_slice(explode('.', (string)InstalledVersions::getVersion('illuminate/validation')), 0, 2));

        if (isset(self::KNOWN_DIFFERENCES[$laravel][$label])) {
            $this->markTestSkipped(sprintf('Laravel %s: %s', $laravel, self::KNOWN_DIFFERENCES[$laravel][$label]));
        }

        if (isset($case['correction'])) {
            // A later version corrects v3.7, so v3.7 must not conform.
            $this->assertFalse(self::conforms($case), "Case conforms on v3.7, so it is not a correction: {$case['correction']}");

            return;
        }

        $validator = self::$factory->make($case['data'], $case['rules']);
        $passes = $validator->passes();

        $this->assertSame(self::sortFailed($case['failed'] ?? []), self::failedRules($validator));
        $this->assertSame($case['valid'], $passes);
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
