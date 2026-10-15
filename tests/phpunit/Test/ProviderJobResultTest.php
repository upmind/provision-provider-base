<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;
use Upmind\ProvisionBase\Provider;
use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\ResultData;
use Upmind\ProvisionBase\ProviderJob;
use Upmind\ProvisionBase\Registry\Data\DataSetRegister;
use Upmind\ProvisionBase\Registry\Data\FunctionRegister;
use Upmind\ProvisionBase\Registry\Data\ProviderRegister;
use Upmind\ProvisionBase\TestStub\TestDataSet;
use Upmind\ProvisionBase\TestStub\TestProvider;

class ProviderJobResultTest extends TestCase
{
    private TestProvider $provider;

    protected function setUp(): void
    {
        DataSet::setValidatorFactory(new Factory(new Translator(new ArrayLoader(), 'en')));
    }

    protected function tearDown(): void
    {
        DataSet::setValidatorFactory(null);
    }

    public function testSuccessResult(): void
    {
        $job = $this->makeJob('succeed');

        $result = $job->execute();

        $this->assertTrue($result->isOk());
        $this->assertSame('Done', $result->getMessage());
        $this->assertSame(['a' => 1], $result->getData());
        $this->assertSame(['b' => 2], array_diff_key($result->getDebug(), ['execution_time' => null]));
        $this->assertIsFloat($result->getDebug()['execution_time']);
        $this->assertSame($result, $job->getResult());
    }

    public function testErrorResult(): void
    {
        $job = $this->makeJob('fail');

        $result = $job->execute();
        $debug = $result->getDebug();

        $this->assertTrue($result->isError());
        $this->assertSame('Provider said no', $result->getMessage());
        $this->assertSame(['reason' => 'x'], $result->getData());
        $this->assertInstanceOf(ProvisionFunctionError::class, $result->getException());
        $this->assertSame(
            ['response', 'provider_output', 'provider_exception', 'message', 'provider_job', 'execution_time'],
            array_keys($debug)
        );
        $this->assertSame('null', $debug['provider_output']);
        $this->assertSame(ProvisionFunctionError::class, $debug['provider_exception']['type']);
        $this->assertSame(['provider' => get_class($this->provider), 'function' => 'fail'], $debug['provider_job']);
        $this->assertSame($result, $job->getResult());
    }

    public function testDestructorExceptionIsAddedToResult(): void
    {
        $job = $this->makeJob('succeed', new RuntimeException('Destructor failure'));

        $result = $job->execute();

        $this->assertTrue($result->isOk());
        $this->assertSame('Destructor failure', $result->getDebug()['provider_destructor_exception']['message']);
        $this->assertSame($result, $job->getResult());
    }

    private function makeJob(string $function, ?RuntimeException $destructorException = null): ProviderJob
    {
        $this->provider = new class (new TestDataSet([])) extends TestProvider {
            public function succeed(TestDataSet $params): ResultData
            {
                return ResultData::create(['a' => 1])->setMessage('Done')->setDebug(['b' => 2]);
            }

            public function fail(TestDataSet $params): ResultData
            {
                throw (new ProvisionFunctionError('Provider said no'))
                    ->withData(['reason' => 'x'])
                    ->withDebug(['response' => 'error']);
            }
        };

        $parameterRegister = $this->createMock(DataSetRegister::class);
        $parameterRegister->method('getClass')->willReturn(TestDataSet::class);
        $returnRegister = $this->createMock(DataSetRegister::class);
        $returnRegister->method('getClass')->willReturn(ResultData::class);

        $functionRegister = $this->createMock(FunctionRegister::class);
        $functionRegister->method('getParameter')->willReturn($parameterRegister);
        $functionRegister->method('getReturn')->willReturn($returnRegister);

        $providerRegister = $this->createMock(ProviderRegister::class);
        $providerRegister->method('hasFunction')->willReturn(true);
        $providerRegister->method('getFunction')->willReturn($functionRegister);

        $provider = $this->createMock(Provider::class);
        $provider->method('getRegister')->willReturn($providerRegister);
        $provider->method('getInstance')->willReturn($this->provider);

        if ($destructorException) {
            $provider->method('unsetInstance')->willThrowException($destructorException);
        }

        return new ProviderJob($provider, $function);
    }
}
