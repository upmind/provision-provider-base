<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SoapFault;
use Throwable;
use Upmind\ProvisionBase\ConnectionErrorClassifier;

class ConnectionErrorClassifierTest extends TestCase
{
    private const URL = 'https://user:secret@example.com:8443/api/v1?api_key=secret#top';
    private const SANITISED_URL = 'https://example.com:8443/api/v1';

    public function testConnectExceptionIsConnectionError(): void
    {
        $e = new ConnectException('Connection refused', new Request('GET', self::URL));

        $this->assertTrue(ConnectionErrorClassifier::isConnectionError($e));
        $this->assertSame($e, ConnectionErrorClassifier::find($e));
    }

    /**
     * @requires extension curl
     * @dataProvider curlConnectionErrorConstants
     */
    #[RequiresPhpExtension('curl')]
    #[DataProvider('curlConnectionErrorConstants')]
    public function testRequestExceptionWithCurlConnectionErrnoIsConnectionError(string $constant): void
    {
        $e = $this->requestException((int)constant($constant));

        $this->assertTrue(ConnectionErrorClassifier::isConnectionError($e));
    }

    public static function curlConnectionErrorConstants(): array
    {
        return [
            'resolve host' => ['CURLE_COULDNT_RESOLVE_HOST'],
            'connect' => ['CURLE_COULDNT_CONNECT'],
            'timeout' => ['CURLE_OPERATION_TIMEOUTED'],
            'ssl connect' => ['CURLE_SSL_CONNECT_ERROR'],
            'ssl ca cert' => ['CURLE_SSL_CACERT'],
            'recv' => ['CURLE_RECV_ERROR'],
        ];
    }

    /**
     * @requires extension curl
     */
    #[RequiresPhpExtension('curl')]
    public function testRequestExceptionWithOtherCurlErrnoIsNotConnectionError(): void
    {
        $e = $this->requestException(CURLE_TOO_MANY_REDIRECTS);

        $this->assertFalse(ConnectionErrorClassifier::isConnectionError($e));
    }

    public function testRequestExceptionWithResponseIsNotConnectionError(): void
    {
        $e = new RequestException('Server error', new Request('GET', self::URL), new Response(500));

        $this->assertFalse(ConnectionErrorClassifier::isConnectionError($e));
        $this->assertNull(ConnectionErrorClassifier::find($e));
    }

    /**
     * @requires extension soap
     * @dataProvider soapConnectionFaults
     */
    #[RequiresPhpExtension('soap')]
    #[DataProvider('soapConnectionFaults')]
    public function testSoapTransportFaultIsConnectionError(string $faultString): void
    {
        $e = new SoapFault('HTTP', $faultString);

        $this->assertTrue(ConnectionErrorClassifier::isConnectionError($e));
    }

    public static function soapConnectionFaults(): array
    {
        return [
            'connect' => ['Could not connect to host'],
            'send' => ['Failed Sending HTTP SOAP request'],
            'timeout' => ['Error Fetching http headers'],
            'body' => ['Error Fetching http body, No Content-Length, connection closed or chunked data'],
        ];
    }

    /**
     * @requires extension soap
     */
    #[RequiresPhpExtension('soap')]
    public function testSoapHttpStatusFaultIsNotConnectionError(): void
    {
        $this->assertFalse(ConnectionErrorClassifier::isConnectionError(new SoapFault('HTTP', 'Not Found')));
    }

    /**
     * @requires extension soap
     */
    #[RequiresPhpExtension('soap')]
    public function testSoapServerFaultIsNotConnectionError(): void
    {
        $e = new SoapFault('Server', 'Could not connect to host');

        $this->assertFalse(ConnectionErrorClassifier::isConnectionError($e));
    }

    public function testFindReturnsWrappedConnectionError(): void
    {
        $connectException = new ConnectException('Connection refused', new Request('GET', self::URL));
        $e = new RuntimeException('API call failed', 0, new LogicException('Request failed', 0, $connectException));

        $this->assertFalse(ConnectionErrorClassifier::isConnectionError($e));
        $this->assertSame($connectException, ConnectionErrorClassifier::find($e));
    }

    /**
     * @dataProvider nonConnectionErrors
     */
    #[DataProvider('nonConnectionErrors')]
    public function testFindReturnsNullForNonConnectionError(Throwable $e): void
    {
        $this->assertNull(ConnectionErrorClassifier::find($e));
    }

    public static function nonConnectionErrors(): array
    {
        return [
            'runtime exception' => [new RuntimeException('Something failed')],
            'wrapped runtime exception' => [new RuntimeException('Outer', 0, new LogicException('Inner'))],
            'request exception without context' => [new RequestException('Bad URI', new Request('GET', self::URL))],
        ];
    }

    /**
     * @requires extension curl
     */
    #[RequiresPhpExtension('curl')]
    public function testGetDebugDataRemovesCredentialsAndQuery(): void
    {
        $e = $this->requestException(CURLE_OPERATION_TIMEOUTED);

        $this->assertSame([
            'type' => RequestException::class,
            'message' => 'cURL error 28: Operation timed out (see https://example.com/errors.html) for '
                . self::SANITISED_URL,
            'url' => self::SANITISED_URL,
            'errno' => CURLE_OPERATION_TIMEOUTED,
        ], ConnectionErrorClassifier::getDebugData($e));
    }

    public function testGetDebugDataForNonHttpException(): void
    {
        $e = new RuntimeException('Connection to ' . self::URL . ' failed');

        $this->assertSame([
            'type' => RuntimeException::class,
            'message' => 'Connection to ' . self::SANITISED_URL . ' failed',
        ], ConnectionErrorClassifier::getDebugData($e));
    }

    public function testSanitiseUrlReplacesUnparsableUrl(): void
    {
        $this->assertSame('[url]', ConnectionErrorClassifier::sanitiseUrl('https:///path?token=secret'));
    }

    private function requestException(int $errno): RequestException
    {
        $message = sprintf(
            'cURL error %d: Operation timed out (see https://example.com/errors.html) for %s',
            $errno,
            self::URL
        );

        return new RequestException($message, new Request('GET', self::URL), null, null, ['errno' => $errno]);
    }
}
