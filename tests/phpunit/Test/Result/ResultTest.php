<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Result;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Upmind\ProvisionBase\Result\Result;

class ResultTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $exception = new RuntimeException('x');
        $result = new Result(Result::STATUS_OK, 'Done', ['a' => 1], ['error_id' => 'id-1'], $exception);

        $this->assertSame(Result::STATUS_OK, $result->getStatus());
        $this->assertSame('Done', $result->getMessage());
        $this->assertSame(['a' => 1], $result->getData());
        $this->assertSame(['error_id' => 'id-1'], $result->getDebug());
        $this->assertSame('id-1', $result->getErrorId());
        $this->assertSame($exception, $result->getException());
    }

    public function testConstructorDoesNotNormalise(): void
    {
        $result = new Result(Result::STATUS_ERROR, '', [], []);

        $this->assertSame('', $result->getMessage());
        $this->assertSame([], $result->getData());
        $this->assertSame([], $result->getDebug());
    }

    public function testConstructorRejectsInvalidStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Result('OK', 'Done');
    }

    public function testIsOkAndIsError(): void
    {
        $ok = new Result(Result::STATUS_OK, 'Done');
        $error = new Result(Result::STATUS_ERROR, 'Failed');

        $this->assertTrue($ok->isOk());
        $this->assertFalse($ok->isError());
        $this->assertFalse($error->isOk());
        $this->assertTrue($error->isError());
    }

    public function testGetErrorIdReturnsNullWithoutDebug(): void
    {
        $this->assertNull((new Result(Result::STATUS_ERROR, 'Failed'))->getErrorId());
    }

    public function testWithDebugValueReturnsCopy(): void
    {
        $exception = new RuntimeException('x');
        $result = new Result(Result::STATUS_OK, 'Done', ['a' => 1], ['b' => 2], $exception);

        $copy = $result->withDebugValue('c', 3)->withDebugValue('b', 4);

        $this->assertSame(['b' => 2], $result->getDebug());
        $this->assertSame(['b' => 4, 'c' => 3], $copy->getDebug());
        $this->assertSame(['a' => 1], $copy->getData());
        $this->assertSame('Done', $copy->getMessage());
        $this->assertSame($exception, $copy->getException());
    }

    public function testWithDebugValueDoesNotUseDotNotation(): void
    {
        $result = (new Result(Result::STATUS_OK, 'Done'))->withDebugValue('a.b', 1);

        $this->assertSame(['a.b' => 1], $result->getDebug());
    }

    public function testGetOutput(): void
    {
        $result = new Result(Result::STATUS_OK, 'Done', ['a' => 1], ['b' => 2]);

        $this->assertSame(
            ['status' => 'ok', 'message' => 'Done', 'data' => ['a' => 1], 'debug' => ['b' => 2]],
            $result->getOutput()
        );
    }

    public function testJsonEncodesListsAsObjects(): void
    {
        $result = new Result(Result::STATUS_OK, 'Done', ['x'], ['data' => ['y'], 'list' => ['z']]);

        $this->assertSame(
            '{"status":"ok","message":"Done","data":{"0":"x"},"debug":{"data":{"0":"y"},"list":["z"]}}',
            json_encode($result)
        );
    }

    public function testJsonKeepsEmptyValues(): void
    {
        $result = new Result(Result::STATUS_OK, 'Done', [], null);

        $this->assertSame('{"status":"ok","message":"Done","data":[],"debug":null}', json_encode($result));
    }

    public function testJsonExcludesException(): void
    {
        $result = new Result(Result::STATUS_ERROR, 'Failed', null, null, new RuntimeException('secret'));

        $this->assertStringNotContainsString('secret', json_encode($result));
    }

    public function testDebugInfoMatchesJson(): void
    {
        $result = new Result(Result::STATUS_OK, 'Done', ['a' => 1]);

        $this->assertEquals($result->jsonSerialize(), $result->__debugInfo());
    }
}
