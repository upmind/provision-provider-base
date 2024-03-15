<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Result;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Upmind\ProvisionBase\Result\Contract\ResultInterface;
use Throwable;
use Upmind\ProvisionBase\Exception\InvalidDataSetException;
use Upmind\ProvisionBase\ProviderJob;

/**
 * Class which encapsulates the Result of a provision request
 */
class Result implements ResultInterface
{
    /**
     * @var string
     */
    protected $status;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var array|null
     */
    protected $data;

    /**
     * @var array|null
     */
    protected $debug;

    /**
     * Any encountered exception.
     *
     * @var Throwable
     */
    protected $exception;

    /**
     * @param string $status Status of the result (ok or error)
     * @param string|null $message User-friendly message to explain the status
     * @param array|null $data Result data
     * @param array|null $debug Debug data
     */
    public function __construct(string $status, ?string $message = null, ?array $data = null, ?array $debug = null)
    {
        $this->status = $status;
        $this->message = $message;
        $this->data = $data;
        $this->debug = $debug;

        $this->standardize();
    }

    /**
     * Create a Result instance for an encountered error
     *
     * @param string $errorMessage
     * @param array|null $errorData
     * @param string|null $error_id
     *
     * @return static
     */
    public static function createErrorResult(
        string $errorMessage,
        ?array $errorData = [],
        ?string $error_id = null
    ): ResultInterface {
        return (new static(ResultInterface::STATUS_ERROR, $errorMessage, $errorData))
            ->setErrorId($error_id);
    }

    /**
     * Create Result object from JSON
     *
     * @param string $json
     *
     * @return static
     */
    public static function createFromJson(string $json): ResultInterface
    {
        return static::createFromArray(json_decode($json, true));
    }

    /**
     * Create Result object from assoc array
     *
     * @param array $array
     *
     * @return static
     */
    public static function createFromArray(array $array): ResultInterface
    {
        if (!is_string($status = Arr::get($array, 'status'))) {
            $status = '';
        }

        if (!is_string($message = Arr::get($array, 'message'))) {
            $message = '';
        }

        $data = Arr::wrap(Arr::get($array, 'data'));
        $debug = Arr::wrap(Arr::get($array, 'debug'));

        return new static($status, $message, $data, $debug);
    }

    /**
     * Create a Result instance using an encountered exception
     *
     * @param Throwable $exception
     * @param string|null $error_id
     *
     * @return static
     */
    public static function createFromException(Throwable $exception, ?string $error_id = null): ResultInterface
    {
        return static::createErrorResult('Critical provision system error encountered', [], $error_id)
            ->withExceptionDebug($exception);
    }

    /**
     * Set an error id for this Result instance.
     *
     * @return static
     */
    public function setErrorId(?string $errorId): ResultInterface
    {
        $errorId = trim($errorId ?? '');

        $this->debug = Arr::wrap($this->debug);

        if (!empty($errorId)) {
            Arr::set($this->debug, 'error_id', $errorId);
        } else {
            Arr::forget($this->debug, 'error_id');

            if (empty($this->debug)) {
                $this->debug = null;
            }
        }

        return $this;
    }

    /**
     * Get this Result instance's error id.
     */
    public function getErrorId(): ?string
    {
        return Arr::get($this->debug ?? [], 'error_id');
    }

    /**
     * Set the Result instance's exception.
     *
     * @return static
     */
    public function setException(Throwable $e): ResultInterface
    {
        $this->exception = $e;

        return $this;
    }

    /**
     * Get the Result instance's exception.
     */
    public function getException(): ?Throwable
    {
        return $this->exception;
    }

    /**
     * Adds the exception to debug data
     *
     * @param Throwable $e
     *
     * @return static
     */
    public function withExceptionDebug(Throwable $e): ResultInterface
    {
        $this->setException($e);

        // Record the first exception in the chain
        while ($previous = $e->getPrevious()) {
            $e = $previous;
        }

        $this->debug = Arr::wrap($this->debug);
        Arr::set($this->debug, 'exception', static::formatException($e));

        return $this->withDebugMessage("Encountered exception: " . get_class($e));
    }

    /**
     * Adds a message to debug data
     *
     * @param string $message
     *
     * @return static
     */
    public function withDebugMessage(string $message): ResultInterface
    {
        $this->debug = Arr::wrap($this->debug);
        Arr::set($this->debug, 'message', $message);

        return $this;
    }

    /**
     * Standardizes the Result status, message, data, and debug fields
     *
     * @return void
     */
    protected function standardize(): void
    {
        $this->standardizeStatus();
        $this->standardizeMessage();
        $this->standardizeData();
        $this->standardizeDebug();
    }

    protected function standardizeStatus(): void
    {
        $this->status = strtolower($this->status);

        if (!in_array($this->status, [ResultInterface::STATUS_OK, ResultInterface::STATUS_ERROR])) {
            $this->status = ResultInterface::STATUS_ERROR;

            if (empty($this->message)) {
                $this->message = 'Result data unknown';
            }
        }
    }

    protected function standardizeData(): void
    {
        $this->data = Arr::wrap($this->data);

        if (empty($this->data)) {
            $this->data = null;
        }
    }

    protected function standardizeMessage(): void
    {
        if (empty($this->message)) {
            switch ($this->status) {
                case ResultInterface::STATUS_OK:
                    $this->message = 'Success';
                    break;
                default:
                    $this->message = 'Unknown provision system error';
            }
        }

        $this->message = ucfirst($this->message);
    }

    protected function standardizeDebug(): void
    {
        $this->debug = Arr::wrap($this->debug);

        if (!empty($this->debug)) {
            $errorId = Arr::get($this->debug, 'error_id');
            if (empty($errorId) || !is_string($errorId)) {
                Arr::forget($this->debug, 'error_id');
            }

            $message = Arr::get($this->debug, 'message');
            if (!is_string($message) || empty($message)) {
                Arr::forget($this->debug, 'message');
            }

            $data = Arr::get($this->debug, 'data');
            if (!is_array($data) || empty($data)) {
                Arr::forget($this->debug, 'data');
            }

            $exception = Arr::get($this->debug, 'exception');
            if ($exception instanceof Throwable) {
                $this->setException($exception);

                $exception = static::formatException($exception);
                Arr::set($this->debug, 'exception', $exception);
            }

            if (!is_array($exception) || empty($exception)) {
                Arr::forget($this->debug, 'exception');
            }
        }

        if (empty($this->debug)) {
            $this->debug = null;
        }
    }

    /**
     * Convert an exception into a formatted associative array
     *
     * @param Throwable $e
     *
     * @return array
     */
    public static function formatException(Throwable $e): array
    {
        return [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'validation_errors' => static::getValidationErrors($e),
            'trace' => static::trimStackTrace($e->getTrace())
        ];
    }

    /**
     * Extract any validation errors from the given exception.
     */
    protected static function getValidationErrors(Throwable $e): ?array
    {
        if (
            $e instanceof ValidationException
            || $e instanceof InvalidDataSetException
        ) {
            return $e->errors();
        }

        return null;
    }

    /**
     * Trim stack trace to only include frames after ProviderJob->execute() if
     * the exception occurred at the Provider level, or otherwise caps the number
     * of stack frames at 5.
     *
     * @param array $trace Stack trace from an exception
     *
     * @return array
     */
    protected static function trimStackTrace(array $trace): array
    {
        foreach ($trace as $i => &$frame) {
            $frame = static::formatStackTraceFrame($frame);

            if (empty($frame['class'])) {
                continue;
            }

            if ($frame['class'] === ProviderJob::class && $frame['function'] === 'execute') {
                $trimIndex = $i;
                break;
            }
        }
        unset($frame);

        if (isset($trimIndex)) {
            $trace = array_slice($trace, 0, $trimIndex);
        } else {
            $trace = array_slice($trace, 0, 5);
        }

        return $trace;
    }

    /**
     * Remove potentially sensitive args from a stack trace frame, and change
     * the index order.
     *
     * @param array $frame Stack trace frame
     *
     * @return array
     */
    protected static function formatStackTraceFrame(array $frame): array
    {
        return [
            'file' => Arr::get($frame, 'file'),
            'line' => Arr::get($frame, 'line'),
            'class' => Arr::get($frame, 'class'),
            'type' => Arr::get($frame, 'type'),
            'function' => Arr::get($frame, 'function'),
        ];
    }

    /**
     * Did the function execute successfully?
     */
    public function isOk(): bool
    {
        return $this->getStatus() === self::STATUS_OK;
    }

    /**
     * Did the function execute unsuccessfully?
     */
    public function isError(): bool
    {
        return $this->getStatus() === self::STATUS_ERROR;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array|null
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * @return array|null
     */
    public function getDebug(): ?array
    {
        return $this->debug;
    }

    /**
     * @return array Return the Result status, message, data, and debug
     */
    public function getOutput(): array
    {
        return [
            'status' => $this->getStatus(),
            'message' => $this->getMessage(),
            'data' => $this->getData(),
            'debug' => $this->getDebug()
        ];
    }

    final public function jsonSerialize(): array
    {
        $output = $this->getOutput();

        //convert arrays to objects for json
        $objectIndexes = ['data', 'debug.data', 'debug'];
        foreach ($objectIndexes as $index) {
            $data = Arr::get($output, $index);
            if (!empty($data)) {
                Arr::set($output, $index, (object)$data);
            }
        }

        return $output;
    }

    public function __debugInfo()
    {
        return $this->jsonSerialize();
    }
}
