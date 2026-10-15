<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Validation;

use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as FactoryContract;
use Illuminate\Support\Str;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Propaganistas\LaravelPhone\PhoneServiceProvider;
use Upmind\ProvisionBase\Laravel\ValidationServiceProvider;
use Upmind\ProvisionBase\Provider\DataSet\DataSet;

/**
 * Runs the validation conformance cases against the Laravel validator with
 * this package's rules.
 */
class ConformanceTest extends TestCase
{
    private static Factory $factory;

    public static function setUpBeforeClass(): void
    {
        $app = new Container();
        Container::setInstance($app);
        $app->singleton('validator', function (Container $app) {
            return new Factory(new Translator(new ArrayLoader(), 'en'), $app);
        });
        $app->alias('validator', FactoryContract::class);

        (new PhoneServiceProvider($app))->register();
        (new ValidationServiceProvider($app))->boot();

        self::$factory = $app->make('validator');
    }

    public static function tearDownAfterClass(): void
    {
        DataSet::setValidatorFactory(null);
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
        $validator = self::$factory->make($case['data'], $case['rules']);
        $passes = $validator->passes();

        $this->assertSame(self::sortFailed($case['failed'] ?? []), self::failedRules($validator));
        $this->assertSame($case['valid'], $passes);
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
