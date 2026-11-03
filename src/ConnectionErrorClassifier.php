<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use SoapFault;
use Throwable;

/**
 * Detects connection-level failures (DNS, connect, timeout, SSL) in exceptions
 * thrown by provider code. Supports Guzzle 6, 7 and 8, other PSR-18 clients and
 * ext-soap.
 */
class ConnectionErrorClassifier
{
    public const REASON_CONNECT = 'connect';
    public const REASON_CONNECT_TIMEOUT = 'connect_timeout';
    public const REASON_TIMEOUT = 'timeout';
    public const REASON_SSL = 'ssl';
    public const REASON_NETWORK = 'network';

    /**
     * cURL error constants which indicate a connection failure, with their
     * reason. ext-curl can be missing, so names resolve at runtime. Only Guzzle
     * 6 and 7 expose the cURL errno.
     *
     * @var array<string,string>
     */
    protected const CURL_ERROR_REASONS = [
        'CURLE_COULDNT_RESOLVE_PROXY' => self::REASON_CONNECT,
        'CURLE_COULDNT_RESOLVE_HOST' => self::REASON_CONNECT,
        'CURLE_COULDNT_CONNECT' => self::REASON_CONNECT,
        'CURLE_OPERATION_TIMEOUTED' => self::REASON_TIMEOUT,
        'CURLE_SSL_CONNECT_ERROR' => self::REASON_SSL,
        'CURLE_SSL_CERTPROBLEM' => self::REASON_SSL,
        'CURLE_SSL_CIPHER' => self::REASON_SSL,
        'CURLE_SSL_CACERT' => self::REASON_SSL,
        'CURLE_SSL_CACERT_BADFILE' => self::REASON_SSL,
        'CURLE_SSL_PINNEDPUBKEYNOTMATCH' => self::REASON_SSL,
        'CURLE_GOT_NOTHING' => self::REASON_NETWORK,
        'CURLE_SEND_ERROR' => self::REASON_NETWORK,
        'CURLE_RECV_ERROR' => self::REASON_NETWORK,
    ];

    /**
     * Guzzle 8 timeout exception classes, with their reason. Class names are
     * strings, so Guzzle 6 and 7 need no such classes.
     *
     * @var array<string,string>
     */
    protected const GUZZLE_TIMEOUT_REASONS = [
        'GuzzleHttp\Exception\ConnectTimeoutException' => self::REASON_CONNECT_TIMEOUT,
        'GuzzleHttp\Exception\NetworkTimeoutException' => self::REASON_TIMEOUT,
        'GuzzleHttp\Exception\ResponseTimeoutException' => self::REASON_TIMEOUT,
    ];

    /**
     * Fault string prefixes of ext-soap transport faults with fault code `HTTP`,
     * with their reason. Other `HTTP` faults carry an HTTP response status, so
     * they do not match.
     *
     * @var array<string,string>
     */
    protected const SOAP_FAULT_REASONS = [
        'Could not connect to host' => self::REASON_CONNECT,
        'Failed Sending HTTP SOAP request' => self::REASON_NETWORK,
        'Error Fetching http headers' => self::REASON_NETWORK,
        'Error Fetching http body' => self::REASON_NETWORK,
    ];

    /**
     * Get the first connection error in the exception chain, if any.
     */
    public static function find(Throwable $e): ?Throwable
    {
        do {
            if (static::isConnectionError($e)) {
                return $e;
            }
        } while ($e = $e->getPrevious());

        return null;
    }

    /**
     * Determine whether the given exception is a connection error. This does
     * not check previous exceptions.
     */
    public static function isConnectionError(Throwable $e): bool
    {
        return static::getReason($e) !== null;
    }

    /**
     * Get the reason of the given connection error: one of the REASON_*
     * constants, or null if it is not a connection error.
     */
    public static function getReason(Throwable $e): ?string
    {
        // Guzzle 6 and 7: the cURL errno is the most specific signal.
        if ($reason = static::getCurlErrorReason($e)) {
            return $reason;
        }

        foreach (static::GUZZLE_TIMEOUT_REASONS as $class => $reason) {
            if (is_a($e, $class)) {
                return $reason;
            }
        }

        if ($e instanceof ConnectException) {
            return self::REASON_CONNECT;
        }

        // Guzzle 8 NetworkException and other PSR-18 clients.
        if ($e instanceof NetworkExceptionInterface) {
            return self::REASON_NETWORK;
        }

        if ($e instanceof SoapFault) {
            return static::getSoapFaultReason($e);
        }

        return null;
    }

    /**
     * Get result data for the given connection error: its reason and, if known,
     * the host. The data contains no URL path, credentials or query strings.
     *
     * @return array<string,string>
     */
    public static function getData(Throwable $e): array
    {
        $data = ['reason' => static::getReason($e) ?? self::REASON_NETWORK];

        if ($host = static::getRequest($e)?->getUri()->getHost()) {
            $data['host'] = $host;
        }

        return $data;
    }

    /**
     * Get debug data for the given connection error. The data contains no URL
     * credentials or query strings.
     *
     * @return array<string,string|int>
     */
    public static function getDebugData(Throwable $e): array
    {
        $debug = [
            'type' => get_class($e),
            'message' => static::sanitiseMessage($e->getMessage()),
        ];

        if ($request = static::getRequest($e)) {
            $debug['url'] = static::sanitiseUrl((string)$request->getUri());
        }

        if ($errno = static::getCurlErrno($e)) {
            $debug['errno'] = $errno;
        }

        return $debug;
    }

    /**
     * Remove credentials, query strings and fragments from each URL in the
     * given message.
     */
    public static function sanitiseMessage(string $message): string
    {
        return preg_replace_callback(
            '#\b[a-z][a-z0-9+.-]*://[^\s\'"<>()]+#i',
            function (array $matches): string {
                return static::sanitiseUrl($matches[0]);
            },
            $message
        ) ?? $message;
    }

    /**
     * Reduce the given URL to its scheme, host, port and path.
     */
    public static function sanitiseUrl(string $url): string
    {
        $parts = parse_url($url);

        if (!is_array($parts) || !isset($parts['host'])) {
            return '[url]';
        }

        return Helper::buildUrl($parts, ['scheme', 'host', 'port', 'path']);
    }

    protected static function getRequest(Throwable $e): ?RequestInterface
    {
        if ($e instanceof RequestException || $e instanceof NetworkExceptionInterface) {
            return $e->getRequest();
        }

        return null;
    }

    /**
     * Get the cURL errno from the Guzzle handler context. Guzzle 8 removed the
     * handler context, so this returns null there.
     */
    protected static function getCurlErrno(Throwable $e): ?int
    {
        if (!$e instanceof RequestException && !$e instanceof ConnectException) {
            return null;
        }

        if (!method_exists($e, 'getHandlerContext')) {
            return null;
        }

        $errno = $e->getHandlerContext()['errno'] ?? null;

        return is_numeric($errno) ? (int)$errno : null;
    }

    protected static function getCurlErrorReason(Throwable $e): ?string
    {
        if (!$errno = static::getCurlErrno($e)) {
            return null;
        }

        foreach (static::CURL_ERROR_REASONS as $name => $reason) {
            if (defined($name) && (int)constant($name) === $errno) {
                return $reason;
            }
        }

        return null;
    }

    protected static function getSoapFaultReason(SoapFault $e): ?string
    {
        if ($e->faultcode !== 'HTTP') {
            return null;
        }

        foreach (static::SOAP_FAULT_REASONS as $prefix => $reason) {
            if (stripos($e->getMessage(), $prefix) === 0) {
                return $reason;
            }
        }

        return null;
    }
}
