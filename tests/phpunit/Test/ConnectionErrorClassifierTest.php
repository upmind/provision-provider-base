<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use ReflectionClass;
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
        $this->assertSame(ConnectionErrorClassifier::REASON_CONNECT, ConnectionErrorClassifier::getReason($e));
    }

    public function testPsr18NetworkExceptionIsConnectionError(): void
    {
        $e = $this->psr18NetworkException();

        $this->assertTrue(ConnectionErrorClassifier::isConnectionError($e));
        $this->assertSame(ConnectionErrorClassifier::REASON_NETWORK, ConnectionErrorClassifier::getReason($e));
        $this->assertSame(['reason' => 'network', 'host' => 'example.com'], ConnectionErrorClassifier::getData($e));
    }

    /**
     * @dataProvider guzzle8TimeoutExceptions
     */
    #[DataProvider('guzzle8TimeoutExceptions')]
    public function testGuzzle8TimeoutExceptionIsConnectionError(string $class, string $reason): void
    {
        if (!class_exists($class)) {
            $this->markTestSkipped("$class requires Guzzle 8");
        }

        $request = new Request('GET', self::URL);
        $e = is_subclass_of($class, RequestException::class)
            ? new $class('Operation timed out', $request, new Response(200))
            : new $class('Operation timed out', $request);

        $this->assertTrue(ConnectionErrorClassifier::isConnectionError($e));
        $this->assertSame($reason, ConnectionErrorClassifier::getReason($e));
    }

    public static function guzzle8TimeoutExceptions(): array
    {
        return [
            'connect timeout' => ['GuzzleHttp\Exception\ConnectTimeoutException', 'connect_timeout'],
            'network timeout' => ['GuzzleHttp\Exception\NetworkTimeoutException', 'timeout'],
            'response timeout' => ['GuzzleHttp\Exception\ResponseTimeoutException', 'timeout'],
        ];
    }

    /**
     * @requires extension curl
     * @dataProvider curlConnectionErrorConstants
     */
    #[RequiresPhpExtension('curl')]
    #[DataProvider('curlConnectionErrorConstants')]
    public function testRequestExceptionWithCurlConnectionErrnoIsConnectionError(string $constant, string $reason): void
    {
        $e = $this->requestException((int)constant($constant));

        $this->assertTrue(ConnectionErrorClassifier::isConnectionError($e));
        $this->assertSame($reason, ConnectionErrorClassifier::getReason($e));
    }

    public static function curlConnectionErrorConstants(): array
    {
        return [
            'resolve host' => ['CURLE_COULDNT_RESOLVE_HOST', 'connect'],
            'connect' => ['CURLE_COULDNT_CONNECT', 'connect'],
            'timeout' => ['CURLE_OPERATION_TIMEOUTED', 'timeout'],
            'ssl connect' => ['CURLE_SSL_CONNECT_ERROR', 'ssl'],
            'ssl ca cert' => ['CURLE_SSL_CACERT', 'ssl'],
            'recv' => ['CURLE_RECV_ERROR', 'network'],
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
        $e = new ServerException('Server error', new Request('GET', self::URL), new Response(500));

        $this->assertFalse(ConnectionErrorClassifier::isConnectionError($e));
        $this->assertNull(ConnectionErrorClassifier::find($e));
    }

    /**
     * @requires extension soap
     * @dataProvider soapConnectionFaults
     */
    #[RequiresPhpExtension('soap')]
    #[DataProvider('soapConnectionFaults')]
    public function testSoapTransportFaultIsConnectionError(string $faultString, string $reason): void
    {
        $e = new SoapFault('HTTP', $faultString);

        $this->assertTrue(ConnectionErrorClassifier::isConnectionError($e));
        $this->assertSame(['reason' => $reason], ConnectionErrorClassifier::getData($e));
    }

    public static function soapConnectionFaults(): array
    {
        return [
            'connect' => ['Could not connect to host', 'connect'],
            'send' => ['Failed Sending HTTP SOAP request', 'network'],
            'timeout' => ['Error Fetching http headers', 'network'],
            'body' => ['Error Fetching http body, No Content-Length, connection closed or chunked data', 'network'],
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

    public function testGetDataHasReasonAndHostOnly(): void
    {
        $e = new ConnectException('Connection refused for ' . self::URL, new Request('GET', self::URL));

        $this->assertSame(['reason' => 'connect', 'host' => 'example.com'], ConnectionErrorClassifier::getData($e));
    }

    public function testGetDebugDataForConnectExceptionWithoutErrno(): void
    {
        $e = new ConnectException('Connection refused for ' . self::URL, new Request('GET', self::URL));

        $this->assertSame([
            'type' => ConnectException::class,
            'message' => 'Connection refused for ' . self::SANITISED_URL,
            'url' => self::SANITISED_URL,
        ], ConnectionErrorClassifier::getDebugData($e));
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

    /**
     * Guzzle 6 and 7 only: Guzzle 8 has no handler context.
     */
    private function requestException(int $errno): RequestException
    {
        if (!method_exists(RequestException::class, 'getHandlerContext')) {
            $this->markTestSkipped('The cURL errno requires the Guzzle 6 or 7 handler context');
        }

        $message = sprintf(
            'cURL error %d: Operation timed out (see https://example.com/errors.html) for %s',
            $errno,
            self::URL
        );

        // Reflection, because the Guzzle 8 constructor has no handler context argument.
        $e = (new ReflectionClass(RequestException::class))
            ->newInstanceArgs([$message, new Request('GET', self::URL), null, null, ['errno' => $errno]]);
        assert($e instanceof RequestException);

        return $e;
    }

    private function psr18NetworkException(): NetworkExceptionInterface
    {
        return new class ('Network failure', new Request('GET', self::URL)) extends RuntimeException implements
            NetworkExceptionInterface
        {
            public function __construct(string $message, private RequestInterface $request)
            {
                parent::__construct($message);
            }

            public function getRequest(): RequestInterface
            {
                return $this->request;
            }
        };
    }
}
