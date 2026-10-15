<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Result;

use InvalidArgumentException;
use JsonSerializable;
use Throwable;

/**
 * Immutable result of a provision function.
 *
 * Use ResultFactory to create instances. The factory normalises the values.
 */
final class Result implements JsonSerializable
{
    /**
     * @var string
     */
    public const STATUS_OK = 'ok';

    /**
     * @var string
     */
    public const STATUS_ERROR = 'error';

    /**
     * @param string $status Result status: ok or error
     * @param string $message User-friendly message
     * @param array|null $data Result data
     * @param array|null $debug Debug data
     * @param Throwable|null $exception Encountered exception. JSON output excludes it.
     *
     * @throws InvalidArgumentException If the status is not ok or error
     */
    public function __construct(
        private readonly string $status,
        private readonly string $message,
        private readonly ?array $data = null,
        private readonly ?array $debug = null,
        private readonly ?Throwable $exception = null
    ) {
        if (!in_array($status, [self::STATUS_OK, self::STATUS_ERROR], true)) {
            throw new InvalidArgumentException(sprintf('Invalid result status: %s', $status));
        }
    }

    /**
     * Return a copy with the given debug value set.
     */
    public function withDebugValue(string $key, mixed $value): self
    {
        $debug = $this->debug ?? [];
        $debug[$key] = $value;

        return new self($this->status, $this->message, $this->data, $debug, $this->exception);
    }

    /**
     * Did the function execute successfully?
     */
    public function isOk(): bool
    {
        return $this->status === self::STATUS_OK;
    }

    /**
     * Did the function execute unsuccessfully?
     */
    public function isError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function getDebug(): ?array
    {
        return $this->debug;
    }

    public function getErrorId(): ?string
    {
        return $this->debug['error_id'] ?? null;
    }

    public function getException(): ?Throwable
    {
        return $this->exception;
    }

    /**
     * @return array Result status, message, data and debug
     */
    public function getOutput(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'data' => $this->data,
            'debug' => $this->debug,
        ];
    }

    /**
     * Encode non-empty data, debug and debug.data as JSON objects.
     */
    public function jsonSerialize(): array
    {
        $output = $this->getOutput();

        if (!empty($output['debug']['data'])) {
            $output['debug']['data'] = (object)$output['debug']['data'];
        }

        foreach (['data', 'debug'] as $key) {
            if (!empty($output[$key])) {
                $output[$key] = (object)$output[$key];
            }
        }

        return $output;
    }

    public function __debugInfo(): array
    {
        return $this->jsonSerialize();
    }
}
