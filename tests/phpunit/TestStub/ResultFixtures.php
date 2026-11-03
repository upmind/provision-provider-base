<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\TestStub;

use Error;
use Exception;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use ReflectionClass;
use ReflectionProperty;
use Throwable;
use Upmind\ProvisionBase\Provider;
use Upmind\ProvisionBase\ProviderJob;

/**
 * Builds deterministic inputs for result tests.
 */
class ResultFixtures
{
    /**
     * Set a fixed file, line and stack trace on the given exception.
     *
     * @template T of Throwable
     *
     * @param T $e
     * @param array<int, array<string, mixed>> $trace
     *
     * @return T
     */
    public static function fixException(Throwable $e, array $trace = []): Throwable
    {
        $class = $e instanceof Exception ? Exception::class : Error::class;

        (new ReflectionProperty($class, 'file'))->setValue($e, '/app/src/Example.php');
        (new ReflectionProperty($class, 'line'))->setValue($e, 10);
        (new ReflectionProperty($class, 'trace'))->setValue($e, $trace);

        return $e;
    }

    /**
     * Stack trace with a ProviderJob::execute() frame at index 2.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function providerTrace(): array
    {
        return [
            [
                'file' => '/app/src/Example.php',
                'line' => 20,
                'function' => 'request',
                'class' => 'Example\\Client',
                'type' => '->',
                'args' => ['secret'],
            ],
            [
                'file' => '/app/src/Example.php',
                'line' => 30,
                'function' => 'create',
                'class' => 'Example\\Provider',
                'type' => '->',
                'args' => ['secret'],
            ],
            [
                'function' => 'execute',
                'class' => ProviderJob::class,
                'type' => '->',
            ],
            [
                'file' => '/app/src/Caller.php',
                'line' => 40,
                'function' => 'run',
                'class' => 'Example\\Caller',
                'type' => '->',
            ],
        ];
    }

    /**
     * Stack trace with 7 frames and no ProviderJob::execute() frame.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function longTrace(): array
    {
        $trace = [['file' => '/app/src/helpers.php', 'line' => 5, 'function' => 'helper']];

        for ($i = 1; $i < 7; $i++) {
            $trace[] = [
                'file' => '/app/src/Example.php',
                'line' => $i * 10,
                'function' => 'step' . $i,
                'class' => 'Example\\Service',
                'type' => '::',
                'args' => [$i],
            ];
        }

        return $trace;
    }

    /**
     * Validation exception with the given messages.
     *
     * @param array<string, string[]> $messages
     */
    public static function validationException(array $messages): ValidationException
    {
        $validator = new Validator(new Translator(new ArrayLoader(), 'en'), [], []);

        foreach ($messages as $key => $keyMessages) {
            foreach ($keyMessages as $message) {
                $validator->errors()->add($key, $message);
            }
        }

        return new ValidationException($validator);
    }

    /**
     * Provider job for the given function, built without a register.
     */
    public static function job(string $function = 'testFunction'): ProviderJob
    {
        $provider = (new ReflectionClass(Provider::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(Provider::class, 'instance'))
            ->setValue($provider, new TestProvider(new TestDataSet([])));

        $job = (new ReflectionClass(ProviderJob::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(ProviderJob::class, 'provider'))->setValue($job, $provider);
        (new ReflectionProperty(ProviderJob::class, 'function'))->setValue($job, $function);

        return $job;
    }
}
