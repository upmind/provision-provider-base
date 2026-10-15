<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase;

use Error;
use Exception;
use Throwable;
use Upmind\ProvisionBase\Exception\CriticalProvisionProviderError;
use Upmind\ProvisionBase\Exception\InvalidDataSetException;
use Upmind\ProvisionBase\Exception\InvalidFunctionParameterDataException;
use Upmind\ProvisionBase\Exception\InvalidFunctionReturnDataException;
use Upmind\ProvisionBase\Exception\InvalidProviderJob;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;
use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\ResultData;
use Upmind\ProvisionBase\Registry\Data\FunctionRegister;
use Upmind\ProvisionBase\Result\Result;
use Upmind\ProvisionBase\Result\ResultFactory;

/**
 * Class for handling the execution of Provider functions
 */
class ProviderJob
{
    /**
     * Provider register/instance wrapper.
     *
     * @var Provider $provider
     */
    protected $provider;

    /**
     * Provision function name.
     *
     * @var string $function
     */
    protected $function;

    /**
     * Provision function register.
     *
     * @var FunctionRegister|null
     */
    protected $functionRegister;

    /**
     * Provision function parameters.
     *
     * @var array|DataSet $parameterData
     */
    protected $parameterData;

    /**
     * Provision function return data.
     *
     * @var DataSet|null $returnData
     */
    protected $returnData;

    /**
     * Provider job result.
     *
     * @var Result
     */
    protected $result;

    /**
     * @param Provider $provider Provider register/instance wrapper
     * @param string $function Provision function name
     * @param array|DataSet $parameterData Provision function parameters
     *
     * @throws InvalidProviderJob If the requested function is not supported
     */
    public function __construct(Provider $provider, string $function, $parameterData = [])
    {
        $this->provider = $provider;
        $this->function = $function;
        $this->parameterData = $parameterData;

        if (!$this->provider->getRegister()->hasFunction($function)) {
            throw InvalidProviderJob::forInvalidFunction($this);
        }
    }

    /**
     * Get the provider register/instance wrapper for this job.
     */
    public function getProvider(): Provider
    {
        return $this->provider;
    }

    /**
     * Get the function name for this job.
     */
    public function getFunction(): string
    {
        return $this->function;
    }

    /**
     * Get the function register for this job.
     */
    public function getFunctionRegister(): FunctionRegister
    {
        if (!isset($this->functionRegister)) {
            $this->functionRegister = $this->provider->getRegister()->getFunction($this->function);
        }

        return $this->functionRegister;
    }

    /**
     * Get the function parameter data set.
     */
    public function getParameterData(): DataSet
    {
        if (!$this->parameterData instanceof DataSet) {
            $this->parameterData = $this->getParameterDataSetClass()::create($this->parameterData);
        }

        return $this->parameterData;
    }

    /**
     * Validate this provider job's parameter data.
     *
     * @throws InvalidFunctionParameterDataException
     */
    protected function validateParameterData(): void
    {
        try {
            $this->getParameterData()->validateIfNotYetValidated();
        } catch (InvalidDataSetException $e) {
            throw InvalidFunctionParameterDataException::fromInvalidDataSet($e);
        }
    }

    /**
     * Get the class name of the job parameter DataSet.
     */
    public function getParameterDataSetClass(): string
    {
        return $this->getFunctionRegister()->getParameter()->getClass();
    }

    /**
     * Get the function return data set.
     */
    public function getReturnData(): ?DataSet
    {
        if (!isset($this->returnData) && !isset($this->result)) {
            $this->execute();
        }

        return $this->returnData ?? null;
    }

    /**
     * Validate this provider job's return data.
     *
     * @throws InvalidFunctionReturnDataException
     */
    protected function validateReturnData(): void
    {
        try {
            $this->getReturnData()->validateIfNotYetValidated();
        } catch (InvalidDataSetException $e) {
            throw InvalidFunctionReturnDataException::fromInvalidDataSet($e);
        }
    }

    /**
     * Get the class name of the job return DataSet.
     */
    public function getReturnDataSetClass(): string
    {
        return $this->getFunctionRegister()->getReturn()->getClass();
    }

    /**
     * Get this provider job's result.
     */
    public function getResult(): Result
    {
        if (!isset($this->result)) {
            $this->execute();
        }

        return $this->result;
    }

    /**
     * Execute the provision function and obtain the job result.
     */
    public function execute(): Result
    {
        if (isset($this->result)) {
            return $this->result;
        }

        $executeStartTime = microtime(true);

        try {
            //validate parameter data
            $this->validateParameterData();

            try {
                //execute function
                $this->returnData = call_user_func(
                    [$this->getProvider()->getInstance(), $this->getFunction()],
                    $this->getParameterData()
                );
            } catch (ProvisionFunctionError $e) {
                throw $e;
            } catch (Exception $e) {
                throw new ProvisionFunctionError('Internal provision provider error', (int)$e->getCode(), $e);
            } catch (Error $e) {
                throw new CriticalProvisionProviderError('Critical provision provider error', (int)$e->getCode(), $e);
            }

            //ensure return data is of the correct type
            if (get_class($this->returnData) !== $this->getReturnDataSetClass()) {
                $this->returnData = $this->getReturnDataSetClass()::create($this->returnData);
            }

            //validate return data
            $this->validateReturnData();

            //create successful job result
            return $this->result = $this->createSuccessResult($this->returnData);
        } catch (Throwable $e) {
            return $this->result = ResultFactory::withProviderJobDebug(
                $this->createErrorResult($e, $this->returnData ?? null),
                $this
            );
        } finally {
            try {
                // trigger destructors to prevent destruct errors from affecting the provision result
                $this->getProvider()->unsetInstance();
            } catch (Throwable $e) {
                $this->result = ResultFactory::withProviderDestructorExceptionDebug($this->result, $e);
            }

            return $this->result = ResultFactory::withExecutionTimeDebug(
                $this->result,
                microtime(true) - $executeStartTime
            );
        }
    }

    /**
     * Create a successful provider result with the given provision function
     * return data.
     */
    protected function createSuccessResult(DataSet $returnData): Result
    {
        if ($returnData instanceof ResultData) {
            return $returnData->toProvisionResult();
        }

        return ResultFactory::ok(null, $returnData->toArray());
    }

    /**
     * Create an error provider result from the given exception.
     *
     * @param Throwable $e Encountered error
     * @param DataSet|array|null $returnData Function return data, if any
     */
    protected function createErrorResult(Throwable $e, $returnData = null): Result
    {
        if ($e instanceof ProvisionFunctionError) {
            $result = $e->toProvisionResult();
        } elseif ($e instanceof InvalidFunctionParameterDataException) {
            $e->setErrorPrefix('parameters');
            $result = ResultFactory::error($e->getMessage(), ['validation_errors' => $e->errors()]);
        } elseif ($e instanceof InvalidFunctionReturnDataException) {
            $e->setErrorPrefix('provider_output');
            $result = ResultFactory::error($e->getMessage());
        } elseif ($e instanceof CriticalProvisionProviderError) {
            $result = ResultFactory::error($e->getMessage());
        } else {
            throw $e; // at this point, any other type of exception is our bad!
        }

        if (isset($returnData) && $returnData instanceof DataSet) {
            $returnData = clone $returnData;
            $returnData->autoValidation(false); //disable validation to get return data values without errors
        }

        return ResultFactory::withProviderExceptionDebug(
            ResultFactory::withProviderOutputDebug($result, $returnData),
            $e
        );
    }

    public function __debugInfo()
    {
        return [
            'provider' => $this->provider,
            'function' => $this->function,
            'parameterData' => $this->parameterData,
            'result' => $this->result,
        ];
    }
}
