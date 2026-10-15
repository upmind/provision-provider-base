<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use Upmind\ProvisionBase\Exception\ProviderConnectionError;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;
use Upmind\ProvisionBase\Provider;
use Upmind\ProvisionBase\ProviderJob;
use Upmind\ProvisionBase\Registry\Data\DataSetRegister;
use Upmind\ProvisionBase\Registry\Data\FunctionRegister;
use Upmind\ProvisionBase\Registry\Data\ProviderRegister;
use Upmind\ProvisionBase\Result\Result;
use Upmind\ProvisionBase\TestStub\TestDataSet;
use Upmind\ProvisionBase\TestStub\TestProvider;

class ProviderJobTest extends TestCase
{
    private Provider&Stub $provider;
    private ProviderRegister&Stub $providerRegister;
    private FunctionRegister&Stub $functionRegister;

    protected function setUp(): void
    {
        $this->providerRegister = $this->createStub(ProviderRegister::class);
        $this->provider = $this->createStub(Provider::class);
        $this->functionRegister = $this->createStub(FunctionRegister::class);
    }

    public function testConstructorCreatesJobSuccessfully(): void
    {
        $this->provider->method('getRegister')->willReturn($this->providerRegister);
        $this->providerRegister->method('hasFunction')->willReturnMap([['testFunction', true]]);

        $job = new ProviderJob($this->provider, 'testFunction', []);

        $this->assertSame($this->provider, $job->getProvider());
        $this->assertSame('testFunction', $job->getFunction());
    }

    public function testGetFunctionRegisterReturnsCorrectRegister(): void
    {
        $this->provider->method('getRegister')->willReturn($this->providerRegister);
        $this->providerRegister->method('hasFunction')->willReturn(true);
        $this->providerRegister->method('getFunction')->willReturnMap([['testFunction', $this->functionRegister]]);

        $job = new ProviderJob($this->provider, 'testFunction');

        $this->assertSame($this->functionRegister, $job->getFunctionRegister());
    }

    public function testGetParameterDataSetClassReturnsCorrectClass(): void
    {
        $dataSetRegister = $this->createStub(DataSetRegister::class);
        $dataSetRegister->method('getClass')->willReturn(TestDataSet::class);

        $this->provider->method('getRegister')->willReturn($this->providerRegister);
        $this->providerRegister->method('hasFunction')->willReturn(true);
        $this->providerRegister->method('getFunction')->willReturn($this->functionRegister);
        $this->functionRegister->method('getParameter')->willReturn($dataSetRegister);

        $job = new ProviderJob($this->provider, 'testFunction');

        $this->assertSame(TestDataSet::class, $job->getParameterDataSetClass());
    }

    public function testExecuteRendersConnectionError(): void
    {
        $result = $this->executeThrowing(new ConnectException(
            'Connection refused for URI https://user:secret@example.com/api?key=secret',
            new Request('GET', 'https://user:secret@example.com/api?key=secret')
        ));

        $this->assertTrue($result->isError());
        $this->assertSame('Provider API connection failed', $result->getMessage());
        $this->assertInstanceOf(ProviderConnectionError::class, $result->getException());
        $this->assertSame([
            'type' => ConnectException::class,
            'message' => 'Connection refused for URI https://example.com/api',
            'url' => 'https://example.com/api',
        ], $result->getDebug()['connection_error']);
        $this->assertSame(
            'Connection refused for URI https://example.com/api',
            $result->getDebug()['provider_exception']['message']
        );
    }

    public function testExecuteRendersWrappedConnectionError(): void
    {
        $connectException = new ConnectException('Connection refused', new Request('GET', 'https://example.com'));

        $result = $this->executeThrowing(new RuntimeException('API call failed', 0, $connectException));

        $this->assertSame('Provider API connection failed', $result->getMessage());
        $this->assertSame(ConnectException::class, $result->getDebug()['connection_error']['type']);
        $this->assertSame(ConnectException::class, $result->getDebug()['provider_exception']['type']);
    }

    public function testExecuteKeepsProvisionFunctionErrorWithConnectionError(): void
    {
        $connectException = new ConnectException('Connection refused', new Request('GET', 'https://example.com'));

        $result = $this->executeThrowing(ProvisionFunctionError::create('Custom error', $connectException));

        $this->assertSame('Custom error', $result->getMessage());
        $this->assertArrayNotHasKey('connection_error', $result->getDebug());
    }

    public function testExecuteRendersOtherExceptionAsInternalError(): void
    {
        $result = $this->executeThrowing(new RuntimeException('Something failed at https://example.com?key=abc'));

        $this->assertSame('Internal provision provider error', $result->getMessage());
        $this->assertArrayNotHasKey('connection_error', $result->getDebug());
        $this->assertSame(
            'Something failed at https://example.com?key=abc',
            $result->getDebug()['provider_exception']['message']
        );
    }

    private function executeThrowing(Throwable $e): Result
    {
        $dataSetRegister = $this->createStub(DataSetRegister::class);
        $dataSetRegister->method('getClass')->willReturn(TestDataSet::class);

        $this->functionRegister->method('getParameter')->willReturn($dataSetRegister);
        $this->functionRegister->method('getReturn')->willReturn($dataSetRegister);
        $this->providerRegister->method('hasFunction')->willReturn(true);
        $this->providerRegister->method('getFunction')->willReturn($this->functionRegister);
        $this->provider->method('getRegister')->willReturn($this->providerRegister);
        $this->provider->method('getInstance')->willReturn(new class ($e) extends TestProvider {
            private Throwable $exception;

            public function __construct(Throwable $exception)
            {
                $this->exception = $exception;
            }

            public function testFunction(): void
            {
                throw $this->exception;
            }
        });

        return (new ProviderJob($this->provider, 'testFunction'))->execute();
    }
}
