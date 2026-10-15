<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Provider\DataSet;

use Illuminate\Contracts\Support\Arrayable;
use Upmind\ProvisionBase\Result\Result;
use Upmind\ProvisionBase\Result\ResultFactory;

class ResultData extends DataSet
{
    /**
     * Success message.
     *
     * @var string $message
     */
    protected $message = 'Operation completed successfully';

    /**
     * Debug data.
     *
     * @var array|null $debug
     */
    protected $debug = null;

    /**
     * @param string $message Success message
     *
     * @return static
     */
    public function setMessage($message)
    {
        if ($message = (string)$message) {
            $this->message = $message;
        }

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param array $data Result data
     *
     * @return static
     */
    public function setData($data)
    {
        $this->__construct($data);

        $this->validator = null;
        $this->isValidated = false;

        return $this;
    }

    public function getData(): array
    {
        return $this->toArray();
    }

    /**
     * @param array|null $debug Debug data
     *
     * @return static
     */
    public function setDebug($debug)
    {
        if ($debug instanceof Arrayable) {
            $debug = $debug->toArray();
        }

        $this->debug = is_array($debug) ? $debug : null;

        return $this;
    }

    public function getDebug(): ?array
    {
        return $this->debug;
    }

    public static function rules(): Rules
    {
        return new Rules();
    }

    /**
     * Create a successful provision result from this result data instance.
     */
    final public function toProvisionResult(): Result
    {
        return ResultFactory::fromResultData($this);
    }
}
