<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test;

use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Provider;
use Upmind\ProvisionBase\ProviderJob;
use Upmind\ProvisionBase\Registry\Data\DataSetRegister;
use Upmind\ProvisionBase\Registry\Data\FunctionRegister;
use Upmind\ProvisionBase\Registry\Data\ProviderRegister;
use Upmind\ProvisionBase\TestStub\TestDataSet;

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
}
