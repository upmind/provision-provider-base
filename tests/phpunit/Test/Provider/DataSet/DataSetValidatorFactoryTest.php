<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Provider\DataSet;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Provider\DataSet\DataSet;

class DataSetValidatorFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        DataSet::setValidatorFactory(null);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
    }

    public function testFallsBackToFacadeRootWhenNotSet(): void
    {
        $factory = new Factory(new Translator(new ArrayLoader(), 'en'));
        $app = new Container();
        $app->instance('validator', $factory);
        Facade::setFacadeApplication($app);

        $this->assertSame($factory, DataSet::getValidatorFactory());
    }

    public function testReturnsSetFactory(): void
    {
        $factory = new Factory(new Translator(new ArrayLoader(), 'en'));

        DataSet::setValidatorFactory($factory);

        $this->assertSame($factory, DataSet::getValidatorFactory());
    }
}
