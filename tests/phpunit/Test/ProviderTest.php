<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test;

use InvalidArgumentException;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Provider;
use Upmind\ProvisionBase\Registry\Data\Contract\ProviderRegisterInterface;
use Upmind\ProvisionBase\TestStub\TestDataSet;
use Upmind\ProvisionBase\TestStub\TestProvider;

class ProviderTest extends TestCase
{
    private ProviderRegisterInterface&Stub $register;
    private TestProvider $instance;

    protected function setUp(): void
    {
        $this->register = $this->createStub(ProviderRegisterInterface::class);
        $this->instance = new TestProvider(new TestDataSet([]));
    }

    public function testConstructorAcceptsRegisterInterface(): void
    {
        $this->register->method('getClass')->willReturn(TestProvider::class);

        $provider = new Provider($this->register, $this->instance);

        $this->assertSame($this->register, $provider->getRegister());
        $this->assertSame($this->instance, $provider->getInstance());
    }

    public function testConstructorRejectsMismatchedClass(): void
    {
        $this->register->method('getClass')->willReturn(TestDataSet::class);

        $this->expectException(InvalidArgumentException::class);

        new Provider($this->register, $this->instance);
    }

    public function testMakeJobChecksFunctionOnRegister(): void
    {
        $register = $this->createMock(ProviderRegisterInterface::class);
        $register->method('getClass')->willReturn(TestProvider::class);
        $register->expects($this->once())
            ->method('hasFunction')
            ->with('testFunction')
            ->willReturn(true);

        $provider = new Provider($register, $this->instance);
        $job = $provider->makeJob('testFunction', []);

        $this->assertSame($provider, $job->getProvider());
        $this->assertSame('testFunction', $job->getFunction());
    }
}
