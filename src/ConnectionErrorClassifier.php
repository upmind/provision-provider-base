<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use SoapFault;
use Throwable;

/**
 * Detects connection-level failures (DNS, connect, timeout, SSL) in exceptions
 * thrown by provider code.
 */
class ConnectionErrorClassifier
{
    /**
     * cURL error constants which indicate a connection failure. ext-curl can be
     * missing, so names resolve at runtime.
     *
     * @var string[]
     */
    protected const CURL_ERROR_CONSTANTS = [
        'CURLE_COULDNT_RESOLVE_PROXY',
        'CURLE_COULDNT_RESOLVE_HOST',
        'CURLE_COULDNT_CONNECT',
        'CURLE_OPERATION_TIMEOUTED',
        'CURLE_SSL_CONNECT_ERROR',
        'CURLE_GOT_NOTHING',
        'CURLE_SEND_ERROR',
        'CURLE_RECV_ERROR',
        'CURLE_SSL_CERTPROBLEM',
        'CURLE_SSL_CIPHER',
        'CURLE_SSL_CACERT',
        'CURLE_SSL_CACERT_BADFILE',
        'CURLE_SSL_PINNEDPUBKEYNOTMATCH',
    ];

    /**
     * Fault string prefixes of ext-soap transport faults with fault code `HTTP`.
     * Other `HTTP` faults carry an HTTP response status, so they do not match.
     *
     * @var string[]
     */
    protected const SOAP_FAULT_PREFIXES = [
        'Could not connect to host',
        'Failed Sending HTTP SOAP request',
        'Error Fetching http headers',
        'Error Fetching http body',
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
        if ($e instanceof ConnectException) {
            return true;
        }

        if ($e instanceof RequestException) {
            return in_array(static::getCurlErrno($e), static::getCurlErrorCodes(), true);
        }

        if ($e instanceof SoapFault) {
            return static::isSoapConnectionFault($e);
        }

        return false;
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

        if ($e instanceof ConnectException || $e instanceof RequestException) {
            $debug['url'] = static::sanitiseUrl((string)$e->getRequest()->getUri());

            if ($errno = static::getCurlErrno($e)) {
                $debug['errno'] = $errno;
            }
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

    /**
     * @param ConnectException|RequestException $e
     */
    protected static function getCurlErrno(Throwable $e): ?int
    {
        $errno = $e->getHandlerContext()['errno'] ?? null;

        return is_numeric($errno) ? (int)$errno : null;
    }

    /**
     * @return int[]
     */
    protected static function getCurlErrorCodes(): array
    {
        $codes = [];

        foreach (static::CURL_ERROR_CONSTANTS as $name) {
            if (defined($name)) {
                $codes[] = (int)constant($name);
            }
        }

        return $codes;
    }

    protected static function isSoapConnectionFault(SoapFault $e): bool
    {
        if ($e->faultcode !== 'HTTP') {
            return false;
        }

        foreach (static::SOAP_FAULT_PREFIXES as $prefix) {
            if (stripos($e->getMessage(), $prefix) === 0) {
                return true;
            }
        }

        return false;
    }
}
