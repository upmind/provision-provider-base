<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Result;

use Exception;
use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Result\ProviderResult;

class ProviderResultTest extends TestCase
{
    public function testCreateFromProviderOutputWithArray(): void
    {
        $output = ['key' => 'value', 'status' => 'ok'];
        $result = ProviderResult::createFromProviderOutput($output);

        $this->assertSame(ProviderResult::STATUS_OK, $result->getStatus());
    }

    public function testCreateFromProviderOutputWithString(): void
    {
        $output = 'simple string output';
        
        $result = ProviderResult::createFromProviderOutput($output);

        $this->assertSame(ProviderResult::STATUS_ERROR, $result->getStatus());
    }

    public function testCreateFromProviderException(): void
    {
        $exception = new Exception('Test exception message');
        
        $result = ProviderResult::createFromProviderException($exception);

        $this->assertSame(ProviderResult::STATUS_ERROR, $result->getStatus());
        $this->assertSame('Critical provider error encountered', $result->getMessage());
    }

    public function testWithProviderExceptionDebugAddsExceptionInfo(): void
    {
        $result = new ProviderResult(ProviderResult::STATUS_OK, 'Test message');
        $exception = new Exception('Test exception');
        
        $resultWithDebug = $result->withProviderExceptionDebug($exception);

        $debug = $resultWithDebug->getDebug();

        $this->assertArrayHasKey('provider_exception', $debug);
        $this->assertEquals('Exception', $debug['provider_exception']['type']);
    }

    public function testCreateErrorResultCreatesErrorResult(): void
    {
        $result = ProviderResult::createErrorResult('Error message', ['error_data']);

        $this->assertSame(ProviderResult::STATUS_ERROR, $result->getStatus());
        $this->assertSame('Error message', $result->getMessage());
        $this->assertSame(['error_data'], $result->getData());
    }
}
