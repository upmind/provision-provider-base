<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Provider;
use Upmind\ProvisionBase\ProviderJob;
use Upmind\ProvisionBase\Registry\Data\DataSetRegister;
use Upmind\ProvisionBase\Registry\Data\FunctionRegister;
use Upmind\ProvisionBase\Registry\Data\ProviderRegister;
use Upmind\ProvisionBase\TestStub\TestDataSet;

class ProviderJobTest extends TestCase
{
    private MockObject|Provider $provider;
    private MockObject|ProviderRegister $providerRegister;
    private MockObject|FunctionRegister $functionRegister;

    protected function setUp(): void
    {
        $this->providerRegister = $this->createMock(ProviderRegister::class);
        $this->provider = $this->createMock(Provider::class);
        $this->functionRegister = $this->createMock(FunctionRegister::class);
    }

    public function testConstructorCreatesJobSuccessfully(): void
    {
        $this->provider->method('getRegister')->willReturn($this->providerRegister);
        $this->providerRegister->method('hasFunction')->with('testFunction')->willReturn(true);

        $job = new ProviderJob($this->provider, 'testFunction', []);

        $this->assertSame($this->provider, $job->getProvider());
        $this->assertSame('testFunction', $job->getFunction());
    }

    public function testGetFunctionRegisterReturnsCorrectRegister(): void
    {
        $this->provider->method('getRegister')->willReturn($this->providerRegister);
        $this->providerRegister->method('hasFunction')->willReturn(true);
        $this->providerRegister->method('getFunction')->with('testFunction')->willReturn($this->functionRegister);

        $job = new ProviderJob($this->provider, 'testFunction');

        $this->assertSame($this->functionRegister, $job->getFunctionRegister());
    }

    public function testGetParameterDataSetClassReturnsCorrectClass(): void
    {
        $dataSetRegister = $this->createMock(DataSetRegister::class);
        $dataSetRegister->expects($this->any())->method('getClass')->willReturn(TestDataSet::class);

        $this->provider->method('getRegister')->willReturn($this->providerRegister);
        $this->providerRegister->method('hasFunction')->willReturn(true);
        $this->providerRegister->method('getFunction')->willReturn($this->functionRegister);
        $this->functionRegister->method('getParameter')->willReturn($dataSetRegister);

        $job = new ProviderJob($this->provider, 'testFunction');

        $this->assertSame(TestDataSet::class, $job->getParameterDataSetClass());
    }
}
