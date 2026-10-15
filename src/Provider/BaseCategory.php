<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Provider;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use LogicException;
use Psr\Http\Message\MessageInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;
use Upmind\ProvisionBase\Provider\Contract\LogsDebugData;
use Upmind\ProvisionBase\Provider\DataSet\ResultData;
use Upmind\ProvisionBase\Provider\DataSet\SystemInfo;

/**
 * The root class all Categories should extend from
 */
abstract class BaseCategory implements Contract\CategoryInterface
{
    /**
     * Debug logger.
     *
     * @var LoggerInterface|null
     */
    protected $log;

    /**
     * System/environment metadata.
     *
     * @var SystemInfo|null
     */
    protected $systemInfo;

    /**
     * Returns the result of a successful provision function.
     *
     * @param string $message A user-friendly success message
     * @param mixed[] $data JSONable data to be passed back to the System Client
     * @param mixed[] $debug JSONable debug data
     *
     * @return ResultData Result data set
     */
    public function okResult($message, $data = [], $debug = []): ResultData
    {
        return ResultData::create($data)
            ->setMessage($message)
            ->setDebug($debug);
    }

    /**
     * Throw an error to fail this provision function execution.
     *
     * @param string $message A user-friendly error message
     * @param mixed[] $data JSONable data to be passed back to the System Client
     * @param mixed[] $debug JSONable debug data
     * @param Throwable $previous Previous exception, if any
     *
     * @throws ProvisionFunctionError
     *
     * @return no-return
     */
    public function errorResult($message, $data = [], $debug = [], $previous = null): void
    {
        throw (new ProvisionFunctionError($message, 0, $previous))
            ->withData($data)
            ->withDebug($debug);
    }

    /**
     * Set the Logger instance to facilitate debug logging.
     */
    final public function setLogger(LoggerInterface $logger): void
    {
        $this->log = $logger;
    }

    /**
     * Get the Logger instance for debug logging. Returns a NullLogger if none is set.
     */
    final public function getLogger(): LoggerInterface
    {
        return $this->log ??= new NullLogger();
    }

    /**
     * Sets the SystemInfo instance.
     */
    final public function setSystemInfo(SystemInfo $systemInfo): void
    {
        $this->systemInfo = $systemInfo;
    }

    /**
     * Get SystemInfo instance containing metadata about the system/runtime environment.
     */
    final public function getSystemInfo(): SystemInfo
    {
        if (!isset($this->systemInfo)) {
            throw new LogicException('SystemInfo only set if Provider or Category implement HasSystemInfo');
        }

        return $this->systemInfo;
    }

    /**
     * Get a guzzle handler stack which logs requests/responses via the provider's
     * injected PSR-3 logger.
     */
    protected function getGuzzleHandlerStack(): HandlerStack
    {
        $stack = HandlerStack::create();

        // Rewinds HTTP message body seek position after the stream has been read by Logger middleware
        $rewindMessageBody = function (MessageInterface $message) {
            $message->getBody()->rewind();
            return $message;
        };
        // Logs Request/Response HTTP messages
        $logger = Middleware::log(
            $this->getLogger(),
            new MessageFormatter(MessageFormatter::DEBUG . PHP_EOL),
            LogLevel::DEBUG
        );

        $stack->push(Middleware::mapRequest($rewindMessageBody), 'Rewind-Request-Stream-After-Logging');
        $stack->push(Middleware::mapResponse($rewindMessageBody), 'Rewind-Response-Stream-After-Logging');
        $stack->push($logger, 'Logger');

        return $stack;
    }
}
