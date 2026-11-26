<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Laravel;

use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as FactoryContract;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Laravel\ValidationServiceProvider;
use Upmind\ProvisionBase\Provider\DataSet\DataSet;

class ValidationServiceProviderTest extends TestCase
{
    private Container $app;

    protected function setUp(): void
    {
        $this->app = new Container();
        $this->app->singleton('validator', function (Container $app) {
            return new Factory(new Translator(new ArrayLoader(), 'en'), $app);
        });
        $this->app->alias('validator', FactoryContract::class);
    }

    protected function tearDown(): void
    {
        DataSet::setValidatorFactory(null);
    }

    public function testRegistersRulesWhenValidatorResolvedBeforeBoot(): void
    {
        $this->app->make('validator');

        (new ValidationServiceProvider($this->app))->boot();

        $this->assertCustomRulesRegistered();
    }

    public function testRegistersRulesWhenValidatorResolvedAfterBoot(): void
    {
        (new ValidationServiceProvider($this->app))->boot();

        $this->assertCustomRulesRegistered();
    }

    public function testInternationalPhoneFailureKeepsItsMessage(): void
    {
        (new ValidationServiceProvider($this->app))->boot();

        /** @var Factory $factory */
        $factory = $this->app->make('validator');
        $validator = $factory->make(['phone' => '07400123456'], ['phone' => 'international_phone']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('InternationalPhone', $validator->failed()['phone']);
        $this->assertSame(['The phone number must begin with +{dialing code}'], $validator->errors()->get('phone'));
    }

    private function assertCustomRulesRegistered(): void
    {
        /** @var Factory $factory */
        $factory = $this->app->make('validator');

        $this->assertTrue($factory->make(['v' => 'example.com'], ['v' => 'domain_name'])->passes());
        $this->assertFalse($factory->make(['v' => 'not a domain'], ['v' => 'domain-name'])->passes());
        $this->assertTrue($factory->make(['v' => 'a-b.c_d'], ['v' => 'alpha_dash_dot'])->passes());
        $this->assertFalse($factory->make(['v' => 'a b'], ['v' => 'alpha-dash-dot'])->passes());
        $this->assertTrue($factory->make(['v' => 'a_b'], ['v' => 'alpha_score'])->passes());
        $this->assertFalse($factory->make(['v' => 'a-b'], ['v' => 'alpha-score'])->passes());
        $this->assertTrue($factory->make(['v' => 'GB'], ['v' => 'country_code'])->passes());
        $this->assertFalse($factory->make(['v' => 'XX'], ['v' => 'country_code'])->passes());
        $this->assertTrue($factory->make(['v' => 4], ['v' => 'step:2'])->passes());
        $this->assertFalse($factory->make(['v' => 3], ['v' => 'step:2'])->passes());
    }
}
