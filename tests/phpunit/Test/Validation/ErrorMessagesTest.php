<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Validation;

use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as FactoryContract;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Propaganistas\LaravelPhone\PhoneServiceProvider;
use Upmind\ProvisionBase\Laravel\ValidationServiceProvider;

/**
 * Checks that `international_phone` and nested data sets give the same result
 * and error messages as v3.7.8. Later versions also report the failed rules.
 */
class ErrorMessagesTest extends TestCase
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
        Facade::setFacadeApplication($app);

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

    /**
     * Get the scenarios with the result of v3.7.8.
     */
    public static function scenarios(): iterable
    {
        $path = __DIR__ . '/fixtures/v3.7.8_error_messages.json';

        foreach (json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) as $scenario) {
            yield $scenario['name'] => [$scenario];
        }
    }

    /**
     * @dataProvider scenarios
     */
    #[DataProvider('scenarios')]
    public function testResultAndMessagesMatchV378(array $scenario): void
    {
        $validator = self::$factory->make(
            $scenario['data'],
            $scenario['rules'],
            $scenario['messages'] ?? [],
            $scenario['attributes'] ?? []
        );

        $this->assertSame($scenario['passes'], $validator->passes());
        $this->assertSame($scenario['errors'], $validator->errors()->toArray());

        // A second run must not duplicate or replace a message.
        $this->assertSame($scenario['passes'], $validator->passes());
        $this->assertSame($scenario['errors'], $validator->errors()->toArray());
    }

    public function testInternationalPhoneReportsFailedRule(): void
    {
        $validator = self::$factory->make(
            ['phone' => '123', 'country' => 'GB'],
            ['phone' => ['international_phone:country', 'max:1']]
        );

        $this->assertFalse($validator->passes());
        $this->assertSame(['InternationalPhone', 'Max'], array_keys($validator->failed()['phone']));
    }

    public function testNestedDataSetReportsFailedRules(): void
    {
        $validator = self::$factory->make(
            ['name' => '', 'contact' => ['email' => 'x']],
            [
                'name' => ['required'],
                'contact' => ['upmind_nested_data_set'],
                'contact.name' => ['required'],
                'contact.email' => ['email'],
            ]
        );

        $this->assertFalse($validator->passes());
        $this->assertSame(
            ['name' => ['Required'], 'contact.name' => ['Required'], 'contact.email' => ['Email']],
            array_map('array_keys', $validator->failed())
        );
    }
}
