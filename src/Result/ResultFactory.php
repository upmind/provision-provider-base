<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Result;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;
use Throwable;
use Upmind\ProvisionBase\Exception\InvalidDataSetException;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;
use Upmind\ProvisionBase\Provider\DataSet\ResultData;
use Upmind\ProvisionBase\ProviderJob;

/**
 * Creates normalised Result instances.
 */
final class ResultFactory
{
    /**
     * Create a result with normalised values.
     *
     * An invalid status becomes error. An empty message gets a default. Empty
     * data or debug becomes null. Invalid known debug values are removed. A
     * Throwable in debug exception or provider_exception becomes the result
     * exception.
     */
    public static function make(
        string $status,
        ?string $message = null,
        ?array $data = null,
        ?array $debug = null
    ): Result {
        $status = strtolower($status);

        if (!in_array($status, [Result::STATUS_OK, Result::STATUS_ERROR], true)) {
            $status = Result::STATUS_ERROR;

            if (empty($message)) {
                $message = 'Unexpected provider function output';
            }
        }

        if (empty($message)) {
            $message = $status === Result::STATUS_OK ? 'Success' : 'Unknown provider function error';
        }

        $exception = null;
        $debug = self::normaliseDebug($debug ?? [], $exception);

        return new Result($status, ucfirst($message), $data ?: null, $debug ?: null, $exception);
    }

    /**
     * Create a successful result.
     */
    public static function ok(?string $message = null, ?array $data = null, ?array $debug = null): Result
    {
        return self::make(Result::STATUS_OK, $message, $data, $debug);
    }

    /**
     * Create an error result.
     */
    public static function error(
        ?string $message = null,
        ?array $data = null,
        ?array $debug = null,
        ?string $errorId = null
    ): Result {
        return self::withErrorId(self::make(Result::STATUS_ERROR, $message, $data, $debug), $errorId);
    }

    /**
     * Create a result from an array with status, message, data and debug keys.
     */
    public static function fromArray(array $array): Result
    {
        $status = $array['status'] ?? null;
        $message = $array['message'] ?? null;

        return self::make(
            is_string($status) ? $status : '',
            is_string($message) ? $message : '',
            Arr::wrap($array['data'] ?? null),
            Arr::wrap($array['debug'] ?? null)
        );
    }

    /**
     * Create a result from a JSON object.
     *
     * @throws JsonException If the JSON is invalid
     * @throws InvalidArgumentException If the JSON is not an object or array
     */
    public static function fromJson(string $json): Result
    {
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($array)) {
            throw new InvalidArgumentException('Result JSON must be an object');
        }

        return self::fromArray($array);
    }

    /**
     * Create a result from raw provider function output.
     *
     * @param bool $outputDebug Add the raw output to debug provider_output
     */
    public static function fromProviderOutput(mixed $output, bool $outputDebug = true): Result
    {
        $result = self::fromArray(Arr::wrap($output));

        if ($outputDebug) {
            return self::withProviderOutputDebug($result, $output);
        }

        return $result;
    }

    /**
     * Create an error result for a system exception.
     */
    public static function fromException(Throwable $e, ?string $errorId = null): Result
    {
        return self::withExceptionDebug(
            self::error('Critical provision system error encountered', null, null, $errorId),
            $e
        );
    }

    /**
     * Create an error result for a provider exception.
     */
    public static function fromProviderException(Throwable $e, ?string $errorId = null): Result
    {
        return self::withProviderExceptionDebug(
            self::error('Critical provider error encountered', null, null, $errorId),
            $e
        );
    }

    /**
     * Create a successful result from provision function return data.
     */
    public static function fromResultData(ResultData $data): Result
    {
        return self::ok($data->getMessage(), $data->getData(), $data->getDebug());
    }

    /**
     * Create an error result from a provision function error.
     */
    public static function fromFunctionError(ProvisionFunctionError $e): Result
    {
        return self::error($e->getMessage(), $e->getData(), $e->getDebug());
    }

    /**
     * Set or remove debug error_id. A blank error id removes it.
     */
    public static function withErrorId(Result $result, ?string $errorId): Result
    {
        $errorId = trim($errorId ?? '');

        if ($errorId !== '') {
            return $result->withDebugValue('error_id', $errorId);
        }

        $debug = $result->getDebug() ?? [];
        unset($debug['error_id']);

        return new Result(
            $result->getStatus(),
            $result->getMessage(),
            $result->getData(),
            $debug ?: null,
            $result->getException()
        );
    }

    /**
     * Set debug message.
     */
    public static function withDebugMessage(Result $result, string $message): Result
    {
        return $result->withDebugValue('message', $message);
    }

    /**
     * Set the result exception. Add the root exception to debug exception.
     */
    public static function withExceptionDebug(Result $result, Throwable $e): Result
    {
        return self::withRootExceptionDebug($result, $e, 'exception');
    }

    /**
     * Set the result exception. Add the root exception to debug provider_exception.
     */
    public static function withProviderExceptionDebug(Result $result, Throwable $e): Result
    {
        return self::withRootExceptionDebug($result, $e, 'provider_exception');
    }

    /**
     * Add the root exception to debug provider_destructor_exception.
     */
    public static function withProviderDestructorExceptionDebug(Result $result, Throwable $e): Result
    {
        return $result->withDebugValue(
            'provider_destructor_exception',
            self::formatException(self::rootException($e))
        );
    }

    /**
     * Add the provider job to debug provider_job.
     */
    public static function withProviderJobDebug(Result $result, ProviderJob $job): Result
    {
        return $result->withDebugValue('provider_job', self::formatProviderJob($job));
    }

    /**
     * Add the JSON-encoded provider output to debug provider_output.
     *
     * Output that fails to encode becomes the string null.
     */
    public static function withProviderOutputDebug(Result $result, mixed $output): Result
    {
        $json = json_encode($output, JSON_INVALID_UTF8_SUBSTITUTE);

        return $result->withDebugValue('provider_output', $json === false ? 'null' : $json);
    }

    /**
     * Add the execution time in seconds to debug execution_time.
     */
    public static function withExecutionTimeDebug(Result $result, float $executionTime): Result
    {
        return $result->withDebugValue('execution_time', round($executionTime, 3));
    }

    /**
     * Convert an exception to an array. The trace excludes function args.
     */
    public static function formatException(Throwable $e): array
    {
        return [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'validation_errors' => self::getValidationErrors($e),
            'trace' => self::trimStackTrace($e->getTrace()),
        ];
    }

    /**
     * Convert a provider job to an array.
     */
    public static function formatProviderJob(ProviderJob $job): array
    {
        return [
            'provider' => get_class($job->getProvider()->getInstance()),
            'function' => $job->getFunction(),
        ];
    }

    /**
     * Remove invalid known debug values and format known objects.
     *
     * @param Throwable|null $exception Set to the last Throwable found
     */
    private static function normaliseDebug(array $debug, ?Throwable &$exception): array
    {
        if (empty($debug['provider_data']) || !is_array($debug['provider_data'])) {
            unset($debug['provider_data']);
        }

        if (($debug['provider_job'] ?? null) instanceof ProviderJob) {
            $debug['provider_job'] = self::formatProviderJob($debug['provider_job']);
        }

        if (empty($debug['provider_job']) || !is_array($debug['provider_job'])) {
            unset($debug['provider_job']);
        }

        foreach (['provider_exception', 'exception'] as $key) {
            if (($debug[$key] ?? null) instanceof Throwable) {
                $exception = $debug[$key];
                $debug[$key] = self::formatException($exception);
            }
        }

        if (!is_string($debug['provider_output'] ?? null)) {
            unset($debug['provider_output']);
        }

        if (empty($debug['error_id']) || !is_string($debug['error_id'])) {
            unset($debug['error_id']);
        }

        if (empty($debug['message']) || !is_string($debug['message'])) {
            unset($debug['message']);
        }

        foreach (['data', 'provider_exception', 'exception'] as $key) {
            if (empty($debug[$key]) || !is_array($debug[$key])) {
                unset($debug[$key]);
            }
        }

        return $debug;
    }

    private static function withRootExceptionDebug(Result $result, Throwable $e, string $key): Result
    {
        $root = self::rootException($e);

        $debug = $result->getDebug() ?? [];
        $debug[$key] = self::formatException($root);
        $debug['message'] = 'Encountered exception: ' . get_class($root);

        return new Result($result->getStatus(), $result->getMessage(), $result->getData(), $debug, $e);
    }

    /**
     * Get the first exception in the chain.
     */
    private static function rootException(Throwable $e): Throwable
    {
        while ($previous = $e->getPrevious()) {
            $e = $previous;
        }

        return $e;
    }

    private static function getValidationErrors(Throwable $e): ?array
    {
        if ($e instanceof ValidationException || $e instanceof InvalidDataSetException) {
            return $e->errors();
        }

        return null;
    }

    /**
     * Keep the frames before ProviderJob::execute(). Without that frame, keep
     * the first 5 frames.
     */
    private static function trimStackTrace(array $trace): array
    {
        $trace = array_map([self::class, 'formatStackTraceFrame'], $trace);

        foreach ($trace as $i => $frame) {
            if ($frame['class'] === ProviderJob::class && $frame['function'] === 'execute') {
                return array_slice($trace, 0, $i);
            }
        }

        return array_slice($trace, 0, 5);
    }

    /**
     * Remove function args from a stack trace frame.
     */
    private static function formatStackTraceFrame(array $frame): array
    {
        return [
            'file' => $frame['file'] ?? null,
            'line' => $frame['line'] ?? null,
            'class' => $frame['class'] ?? null,
            'type' => $frame['type'] ?? null,
            'function' => $frame['function'] ?? null,
        ];
    }
}
