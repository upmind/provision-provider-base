<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Result;

use Exception;
use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Upmind\ProvisionBase\Exception\InvalidDataSetException;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;
use Upmind\ProvisionBase\Provider\DataSet\ResultData;
use Upmind\ProvisionBase\ProviderJob;
use Upmind\ProvisionBase\Result\Result;
use Upmind\ProvisionBase\Result\ResultFactory;
use Upmind\ProvisionBase\TestStub\ResultFixtures;
use Upmind\ProvisionBase\TestStub\TestProvider;

class ResultFactoryTest extends TestCase
{
    public function testOkDefaults(): void
    {
        $result = ResultFactory::ok();

        $this->assertTrue($result->isOk());
        $this->assertSame('Success', $result->getMessage());
        $this->assertNull($result->getData());
        $this->assertNull($result->getDebug());
        $this->assertNull($result->getException());
    }

    public function testErrorDefaults(): void
    {
        $result = ResultFactory::error();

        $this->assertTrue($result->isError());
        $this->assertSame('Unknown provider function error', $result->getMessage());
    }

    public function testErrorWithDataDebugAndErrorId(): void
    {
        $result = ResultFactory::error('failed', ['a' => 1], ['b' => 2], ' id-1 ');

        $this->assertSame('Failed', $result->getMessage());
        $this->assertSame(['a' => 1], $result->getData());
        $this->assertSame(['b' => 2, 'error_id' => 'id-1'], $result->getDebug());
        $this->assertSame('id-1', $result->getErrorId());
    }

    public function testMakeLowercasesStatus(): void
    {
        $this->assertSame(Result::STATUS_ERROR, ResultFactory::make('ERROR')->getStatus());
    }

    public function testMakeTreatsInvalidStatusAsError(): void
    {
        $result = ResultFactory::make('pending');

        $this->assertTrue($result->isError());
        $this->assertSame('Unexpected provider function output', $result->getMessage());
    }

    public function testMakeSetsExceptionFromDebugThrowable(): void
    {
        $exception = new RuntimeException('x');

        $result = ResultFactory::make('error', 'm', null, ['provider_exception' => $exception]);

        $this->assertSame($exception, $result->getException());
        $this->assertSame('x', $result->getDebug()['provider_exception']['message']);
    }

    public function testMakePrefersDebugExceptionOverProviderException(): void
    {
        $system = new RuntimeException('system');

        $result = ResultFactory::make('error', 'm', null, [
            'exception' => $system,
            'provider_exception' => new RuntimeException('provider'),
        ]);

        $this->assertSame($system, $result->getException());
    }

    public function testMakeFormatsProviderJob(): void
    {
        $result = ResultFactory::make('ok', null, null, ['provider_job' => ResultFixtures::job('create')]);

        $this->assertSame(
            ['provider' => TestProvider::class, 'function' => 'create'],
            $result->getDebug()['provider_job']
        );
    }

    public function testFromArrayRoundTripsJsonOutput(): void
    {
        $result = ResultFactory::fromProviderException(new RuntimeException('x'), 'id-1');

        $copy = ResultFactory::fromJson(json_encode($result));

        $this->assertSame(json_encode($result), json_encode($copy));
        $this->assertSame('id-1', $copy->getErrorId());
    }

    public function testFromJsonRejectsInvalidJson(): void
    {
        $this->expectException(JsonException::class);

        ResultFactory::fromJson('{');
    }

    public function testFromJsonRejectsScalar(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ResultFactory::fromJson('"ok"');
    }

    public function testFromProviderOutputWithArray(): void
    {
        $result = ResultFactory::fromProviderOutput(['key' => 'value', 'status' => 'ok']);

        $this->assertTrue($result->isOk());
        $this->assertSame('{"key":"value","status":"ok"}', $result->getDebug()['provider_output']);
    }

    public function testFromProviderOutputWithString(): void
    {
        $result = ResultFactory::fromProviderOutput('simple string output');

        $this->assertTrue($result->isError());
        $this->assertSame('Unexpected provider function output', $result->getMessage());
    }

    public function testFromProviderOutputWithoutDebug(): void
    {
        $this->assertNull(ResultFactory::fromProviderOutput(['status' => 'ok'], false)->getDebug());
    }

    public function testFromExceptionSetsException(): void
    {
        $exception = new RuntimeException('outer', 0, new Exception('inner'));

        $result = ResultFactory::fromException($exception, 'id-1');

        $this->assertTrue($result->isError());
        $this->assertSame('Critical provision system error encountered', $result->getMessage());
        $this->assertSame($exception, $result->getException());
        $this->assertSame('inner', $result->getDebug()['exception']['message']);
        $this->assertSame('Encountered exception: Exception', $result->getDebug()['message']);
        $this->assertSame('id-1', $result->getErrorId());
    }

    public function testFromProviderExceptionSetsException(): void
    {
        $exception = new Exception('Test exception message');

        $result = ResultFactory::fromProviderException($exception);

        $this->assertTrue($result->isError());
        $this->assertSame('Critical provider error encountered', $result->getMessage());
        $this->assertSame($exception, $result->getException());
        $this->assertSame(Exception::class, $result->getDebug()['provider_exception']['type']);
    }

    public function testFromResultData(): void
    {
        $data = ResultData::create(['a' => 1], false)->setMessage('done')->setDebug(['b' => 2]);

        $result = ResultFactory::fromResultData($data);

        $this->assertTrue($result->isOk());
        $this->assertSame('Done', $result->getMessage());
        $this->assertSame(['a' => 1], $result->getData());
        $this->assertSame(['b' => 2], $result->getDebug());
    }

    public function testFromFunctionError(): void
    {
        $error = (new ProvisionFunctionError('no'))->withData(['a' => 1])->withDebug(['b' => 2]);

        $result = ResultFactory::fromFunctionError($error);

        $this->assertTrue($result->isError());
        $this->assertSame('No', $result->getMessage());
        $this->assertSame(['a' => 1], $result->getData());
        $this->assertSame(['b' => 2], $result->getDebug());
        $this->assertNull($result->getException());
    }

    public function testWithErrorIdRemovesBlankId(): void
    {
        $result = ResultFactory::error('m', null, ['a' => 1], 'id-1');

        $this->assertSame(['a' => 1], ResultFactory::withErrorId($result, ' ')->getDebug());
        $this->assertSame(['a' => 1, 'error_id' => 'id-1'], $result->getDebug());
    }

    public function testWithMethodsDoNotChangeOriginal(): void
    {
        $result = ResultFactory::ok();

        ResultFactory::withDebugMessage($result, 'dbg');
        ResultFactory::withErrorId($result, 'id-1');
        ResultFactory::withExceptionDebug($result, new RuntimeException('x'));
        ResultFactory::withProviderExceptionDebug($result, new RuntimeException('x'));
        ResultFactory::withProviderDestructorExceptionDebug($result, new RuntimeException('x'));
        ResultFactory::withProviderJobDebug($result, ResultFixtures::job());
        ResultFactory::withProviderOutputDebug($result, ['a' => 1]);
        ResultFactory::withExecutionTimeDebug($result, 1.0);

        $this->assertNull($result->getDebug());
        $this->assertNull($result->getException());
    }

    public function testWithProviderDestructorExceptionDebugKeepsException(): void
    {
        $exception = new RuntimeException('provider');
        $result = ResultFactory::fromProviderException($exception);

        $result = ResultFactory::withProviderDestructorExceptionDebug($result, new RuntimeException('destructor'));

        $this->assertSame($exception, $result->getException());
        $this->assertSame('destructor', $result->getDebug()['provider_destructor_exception']['message']);
    }

    public function testWithProviderOutputDebugEncodesFailureAsNull(): void
    {
        $result = ResultFactory::withProviderOutputDebug(ResultFactory::ok(), NAN);

        $this->assertSame('null', $result->getDebug()['provider_output']);
    }

    public function testWithProviderOutputDebugKeepsZero(): void
    {
        $result = ResultFactory::withProviderOutputDebug(ResultFactory::ok(), 0);

        $this->assertSame('0', $result->getDebug()['provider_output']);
    }

    public function testWithExecutionTimeDebugRounds(): void
    {
        $result = ResultFactory::withExecutionTimeDebug(ResultFactory::ok(), 1.23456);

        $this->assertSame(1.235, $result->getDebug()['execution_time']);
    }

    public function testFormatExceptionIncludesValidationErrors(): void
    {
        $exception = new InvalidDataSetException(ResultFixtures::validationException(['a' => ['A is required']]));

        $this->assertSame(['a' => ['A is required']], ResultFactory::formatException($exception)['validation_errors']);
    }

    public function testFormatExceptionTrimsTraceAtProviderJob(): void
    {
        $exception = ResultFixtures::fixException(new RuntimeException('x'), ResultFixtures::providerTrace());

        $trace = ResultFactory::formatException($exception)['trace'];

        $this->assertCount(2, $trace);
        $this->assertNotContains(ProviderJob::class, array_column($trace, 'class'));
        $this->assertArrayNotHasKey('args', $trace[0]);
    }

    public function testFormatExceptionKeepsFiveFrames(): void
    {
        $exception = ResultFixtures::fixException(new RuntimeException('x'), ResultFixtures::longTrace());

        $this->assertCount(5, ResultFactory::formatException($exception)['trace']);
    }
}
