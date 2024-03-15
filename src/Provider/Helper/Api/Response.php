<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Provider\Helper\Api;

use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface as Psr7Response;
use Upmind\ProvisionBase\Provider\Helper\Api\Contract\RequestInterface;
use Upmind\ProvisionBase\Provider\Helper\Api\Contract\ResponseInterface as ApiResponse;

class Response implements ApiResponse
{
    /**
     * @var \Psr\Http\Message\ResponseInterface
     */
    protected $psr7Response;

    /**
     * Original request.
     *
     * @var \Upmind\ProvisionBase\Provider\Helper\Api\Contract\RequestInterface
     */
    protected $request;

    /**
     * @var int
     */
    protected $httpCode;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var object
     */
    protected $body;

    /**
     * @param \Psr\Http\Message\ResponseInterface $psr7Response
     * @param \Upmind\ProvisionBase\Provider\Helper\Api\Contract\RequestInterface $request
     */
    public function __construct(Psr7Response $psr7Response, ?RequestInterface $request = null)
    {
        $this->psr7Response = $psr7Response;
        $this->request = $request;
        $this->processResponse();
    }

    /**
     * Returns the request responsible for this response, if available.
     */
    public function getRequest(): ?RequestInterface
    {
        return $this->request;
    }

    /**
     * Extract required data from Psr7 Response object.
     *
     * @return void
     */
    protected function processResponse()
    {
        $this->setHttpCode();
        $this->setBody();
        $this->setMessage();
    }

    protected function setHttpCode()
    {
        $this->httpCode = $this->psr7Response->getStatusCode();
    }

    protected function setBody()
    {
        $responseJson = $this->psr7Response->getBody()->__toString();
        $this->body = (object)json_decode($responseJson)
            ?? (object)[];
    }

    protected function setMessage()
    {
        $this->message = $this->getBodyAssoc('message')
            ?? $this->getDefaultMessage();
    }

    /**
     * @return string
     */
    protected function getDefaultMessage(): string
    {
        if ($this->isClientError()) {
            return $this->psr7Response->getReasonPhrase()
                ?? 'Unknown Request Error';
        }

        if ($this->isRedirect()) {
            return 'Unexpected Redirect';
        }

        return 'Unknown Error';
    }

    /**
     * Determine if response HTTP code is in same range as given
     * http code
     *
     * @param int $httpCodeRange
     *
     * @return bool
     */
    protected function httpCodeIsInRange(int $httpCodeRange): bool
    {
        $lowerBound = floor(($httpCodeRange / 100)) * 100;
        $upperBound = $lowerBound + 100;

        return $lowerBound <= $this->httpCode && $this->httpCode < $upperBound;
    }

    /**
     * Determine whether HTTP code is in the 200 range, and response body
     * indicates success.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->httpCodeIsInRange(200);
    }

    /**
     * Determine whether HTTP code is in the 300 range
     *
     * @return bool
     */
    public function isRedirect(): bool
    {
        return $this->httpCodeIsInRange(300);
    }

    /**
     * Determine whether HTTP code is in the 400 range
     *
     * @return bool
     */
    public function isClientError(): bool
    {
        return $this->httpCodeIsInRange(400);
    }

    /**
     * Determine whether HTTP code is in the 500 range
     *
     * @return bool
     */
    public function isServerError(): bool
    {
        return $this->httpCodeIsInRange(500);
    }

    /**
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getPsr7(): Psr7Response
    {
        return $this->psr7Response;
    }

    /**
     * @return int
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Returns the Response json body
     *
     * @return object
     */
    public function getBody(): object
    {
        return $this->body;
    }

    /**
     * Returns the Response json body, as an associative array.
     *
     * Optionally returns the given index of the array.
     *
     * @param string $index The index of the array to return
     * @param mixed $default The default value to return if $index is invalid
     *
     * @return array|mixed
     */
    public function getBodyAssoc(?string $index = null, $default = null)
    {
        $data = json_decode(json_encode($this->getBody()), true);

        if ($index) {
            return Arr::get($data, $index, $default);
        }

        return $data;
    }
}
