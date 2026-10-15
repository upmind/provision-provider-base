<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Result;

use Illuminate\Support\Arr;
use Throwable;
use Upmind\ProvisionBase\ConnectionErrorClassifier;
use Upmind\ProvisionBase\Exception\ProviderConnectionError;
use Upmind\ProvisionBase\ProviderJob;
use Upmind\ProvisionBase\Result\Contract\ResultInterface;

/**
 * Class which encapsulates the Result of a ProviderJob
 */
class ProviderResult extends Result implements ResultInterface
{
    /**
     * Create a Result instance for a ProviderJob using the output of a Provider function
     *
     * @param mixed $output Provider function output
     *
     * @return self
     */
    public static function createFromProviderOutput($output, bool $outputDebug = true): self
    {
        $result = self::createFromArray(Arr::wrap($output));

        if ($outputDebug) {
            return $result->withProviderOutputDebug($output);
        }

        return $result;
    }

    /**
     * Create a Result instance for a ProviderJob using an encountered exception
     *
     * @param Throwable $exception
     *
     * @return self
     */
    public static function createFromProviderException(Throwable $exception, ?string $errorId = null): self
    {
        return self::createErrorResult('Critical provider error encountered', [], $errorId)
            ->withProviderExceptionDebug($exception);
    }

    /**
     * Adds the Provider exception to debug data. For a connection error, the
     * message contains no URL credentials or query strings.
     *
     * @param Throwable $e
     *
     * @return self
     */
    public function withProviderExceptionDebug(Throwable $e): self
    {
        $this->setException($e);
        $isConnectionError = $e instanceof ProviderConnectionError;

        // Record the first exception in the chain
        while ($previous = $e->getPrevious()) {
            $e = $previous;
        }

        $exceptionDebug = self::formatException($e);

        if ($isConnectionError) {
            $exceptionDebug['message'] = ConnectionErrorClassifier::sanitiseMessage($exceptionDebug['message']);
        }

        $this->debug = Arr::wrap($this->debug);
        Arr::set($this->debug, 'provider_exception', $exceptionDebug);

        return $this->withDebugMessage("Encountered exception: " . get_class($e));
    }

    /**
     * Adds a Provider destructor exception to debug data
     *
     * @param Throwable $e
     *
     * @return self
     */
    public function withProviderDestructorExceptionDebug(Throwable $e): self
    {
        // Record the first exception in the chain
        while ($previous = $e->getPrevious()) {
            $e = $previous;
        }

        $this->debug = Arr::wrap($this->debug);
        Arr::set($this->debug, 'provider_destructor_exception', self::formatException($e));

        return $this;
    }

    /**
     * Adds the ProviderJob to debug data
     *
     * @param ProviderJob $job
     *
     * @return self
     */
    public function withProviderJobDebug(ProviderJob $job): self
    {
        $this->debug = Arr::wrap($this->debug);
        Arr::set($this->debug, 'provider_job', self::formatProviderJob($job));

        return $this;
    }

    /**
     * Adds the raw Provider function output to debug data
     *
     * @param mixed $providerOutput
     *
     * @return self
     */
    public function withProviderOutputDebug($providerOutput): self
    {
        $providerOutput = json_encode($providerOutput, JSON_INVALID_UTF8_SUBSTITUTE) ?? 'null';

        $this->debug = Arr::wrap($this->debug);
        Arr::set($this->debug, 'provider_output', $providerOutput);

        return $this;
    }

    public function withExecutionTimeDebug(float $executionTime): self
    {
        $this->debug = Arr::wrap($this->debug);
        Arr::set($this->debug, 'execution_time', round($executionTime, 3));

        return $this;
    }

    protected function standardizeStatus(): void
    {
        $this->status = strtolower($this->status);

        if (!in_array($this->status, [ResultInterface::STATUS_OK, ResultInterface::STATUS_ERROR])) {
            $this->status = ResultInterface::STATUS_ERROR;

            if (empty($this->message)) {
                $this->message = 'Unexpected provider function output';
            }
        }

        parent::standardizeStatus();
    }

    protected function standardizeMessage(): void
    {
        if (empty($this->message)) {
            switch ($this->status) {
                case ResultInterface::STATUS_OK:
                    $this->message = 'Success';
                    break;
                default:
                    $this->message = 'Unknown provider function error';
            }
        }

        parent::standardizeMessage();
    }

    protected function standardizeDebug(): void
    {
        $this->debug = Arr::wrap($this->debug);

        if (!empty($this->debug)) {
            $providerData = Arr::get($this->debug, 'provider_data');
            if (!is_array($providerData) || empty($providerData)) {
                Arr::forget($this->debug, 'provider_data');
            }

            $providerJob = Arr::get($this->debug, 'provider_job');
            if ($providerJob instanceof ProviderJob) {
                $providerJob = self::formatProviderJob($providerJob);
                Arr::set($this->debug, 'provider_job', $providerJob);
            }

            if (!is_array($providerJob) || empty($providerJob)) {
                Arr::forget($this->debug, 'provider_job');
            }

            $providerException = Arr::get($this->debug, 'provider_exception');
            if ($providerException instanceof Throwable) {
                $this->setException($providerException);

                $providerException = self::formatException($providerException);
                Arr::set($this->debug, 'provider_exception', $providerException);
            }

            if (!is_array($providerException) || empty($providerException)) {
                Arr::forget($this->debug, 'provider_exception');
            }

            $providerOutput = Arr::get($this->debug, 'provider_output');
            if (!is_string($providerOutput)) {
                Arr::forget($this->debug, 'provider_output');
            }
        }

        parent::standardizeDebug();
    }

    /**
     * Convert a ProviderJob into a formatted associative array
     *
     * @param ProviderJob $job
     *
     * @return array
     */
    public static function formatProviderJob(ProviderJob $job): array
    {
        return [
            'provider' => get_class($job->getProvider()->getInstance()),
            'function' => $job->getFunction(),
            // 'params' => $job->getParams()
        ];
    }
}
