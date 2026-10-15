<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Result;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use Upmind\ProvisionBase\Exception\InvalidDataSetException;
use Upmind\ProvisionBase\Exception\InvalidFunctionParameterDataException;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;
use Upmind\ProvisionBase\Provider\DataSet\ResultData;
use Upmind\ProvisionBase\Result\Result;
use Upmind\ProvisionBase\Result\ResultFactory;
use Upmind\ProvisionBase\TestStub\ResultFixtures as F;

/**
 * Compares JSON output with fixtures captured from the ProviderResult class
 * before it was removed.
 */
class LegacyJsonParityTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/fixtures';

    /**
     * @dataProvider scenarioProvider
     */
    #[DataProvider('scenarioProvider')]
    public function testJsonMatchesLegacyFixture(string $name): void
    {
        $result = self::scenarios()[$name]();

        $this->assertSame(
            file_get_contents(self::FIXTURE_DIR . "/$name.json"),
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    public function testEveryFixtureHasAScenario(): void
    {
        $fixtures = array_map(
            fn (string $path) => basename($path, '.json'),
            glob(self::FIXTURE_DIR . '/*.json')
        );

        $this->assertEqualsCanonicalizing($fixtures, array_keys(self::scenarios()));
    }

    public static function scenarioProvider(): array
    {
        $names = array_keys(self::scenarios());

        return array_combine($names, array_map(fn (string $name) => [$name], $names));
    }

    private static function exception(
        string $message = 'Example failure',
        int $code = 0,
        ?Throwable $previous = null,
        array $trace = []
    ): RuntimeException {
        return F::fixException(new RuntimeException($message, $code, $previous), $trace);
    }

    /**
     * @return array<string, callable(): Result>
     */
    private static function scenarios(): array
    {
        $ex = [self::class, 'exception'];

        return [
            'make_ok_defaults' => fn () => ResultFactory::make('ok'),
            'make_ok_uppercase_status' => fn () => ResultFactory::make('OK', 'done', ['a' => 1, 'b' => ['c' => 2]]),
            'make_error_defaults' => fn () => ResultFactory::make('error'),
            'make_invalid_status' => fn () => ResultFactory::make('foo'),
            'make_invalid_status_with_message' => fn () => ResultFactory::make('foo', 'bar'),
            'make_ucfirst_message' => fn () => ResultFactory::make('ok', 'hello world'),
            'make_zero_message' => fn () => ResultFactory::make('error', '0'),
            'make_empty_data' => fn () => ResultFactory::make('ok', 'm', [], []),
            'make_list_data' => fn () => ResultFactory::make('ok', 'm', ['x', 'y']),
            'make_invalid_debug' => fn () => ResultFactory::make('ok', 'm', null, [
                'error_id' => '',
                'message' => 123,
                'data' => 'x',
                'exception' => 'str',
                'provider_data' => [],
                'provider_job' => 'x',
                'provider_exception' => [],
                'provider_output' => 5,
                'custom' => 'bar',
            ]),
            'make_valid_debug' => fn () => ResultFactory::make('ok', 'm', null, [
                'error_id' => 'abc',
                'message' => 'dbg',
                'data' => ['k' => 'v'],
                'provider_data' => ['p' => 1],
                'provider_output' => '{"x":1}',
                'custom' => [1, 2],
            ]),
            'make_debug_list_data' => fn () => ResultFactory::make('ok', 'm', null, ['data' => ['x', 'y']]),
            'make_debug_throwables' => fn () => ResultFactory::make('error', 'm', null, [
                'exception' => $ex('System failure'),
                'provider_exception' => $ex('Provider failure', 5),
                'provider_job' => F::job(),
            ]),
            'error_with_data' => fn () => ResultFactory::error('Error message', ['error_data']),
            'error_with_error_id' => fn () => ResultFactory::error('Error message', errorId: '  id-1 '),
            'error_with_blank_error_id' => fn () => ResultFactory::error('Error message', errorId: '  '),
            'error_id_removed' => fn () => ResultFactory::withErrorId(
                ResultFactory::error('m', errorId: 'x'),
                null
            ),
            'from_exception' => fn () => ResultFactory::fromException($ex('System failure'), 'id-2'),
            'from_exception_chain' => fn () => ResultFactory::fromException(
                $ex('Outer', 0, $ex('Inner', 3, null, F::longTrace()))
            ),
            'from_provider_exception' => fn () => ResultFactory::fromProviderException(
                $ex('Provider failure', 0, null, F::providerTrace())
            ),
            'from_provider_exception_chain' => fn () => ResultFactory::fromProviderException(
                $ex('Outer', 0, $ex('Inner', 0, null, F::providerTrace())),
                'id-3'
            ),
            'from_provider_output_ok' => fn () => ResultFactory::fromProviderOutput([
                'status' => 'ok',
                'message' => 'm',
                'data' => ['x' => 1],
            ]),
            'from_provider_output_string' => fn () => ResultFactory::fromProviderOutput('simple string output'),
            'from_provider_output_null' => fn () => ResultFactory::fromProviderOutput(null),
            'from_provider_output_invalid_utf8' => fn () => ResultFactory::fromProviderOutput(
                ['status' => 'ok', 'x' => "a\xB1b"]
            ),
            'from_provider_output_no_debug' => fn () => ResultFactory::fromProviderOutput(
                ['status' => 'error', 'data' => 'scalar', 'debug' => 'scalar'],
                false
            ),
            'from_array_non_string' => fn () => ResultFactory::fromArray([
                'status' => 1,
                'message' => ['x'],
                'data' => 'scalar',
                'debug' => ['message' => 'dbg', 'provider_output' => 'null'],
            ]),
            'from_json' => fn () => ResultFactory::fromJson(json_encode([
                'status' => 'error',
                'message' => 'Failed',
                'data' => ['a' => 1],
                'debug' => [
                    'error_id' => 'id-4',
                    'message' => 'Encountered exception: RuntimeException',
                    'provider_exception' => ['type' => 'RuntimeException', 'message' => 'x'],
                    'provider_job' => ['provider' => 'Example\\Provider', 'function' => 'create'],
                    'execution_time' => 0.5,
                ],
            ])),
            'debug_message' => fn () => ResultFactory::withDebugMessage(ResultFactory::ok(), 'dbg'),
            'exception_debug' => fn () => ResultFactory::withExceptionDebug(ResultFactory::ok(), $ex()),
            'provider_exception_debug' => fn () => ResultFactory::withProviderExceptionDebug(
                ResultFactory::ok('m'),
                $ex()
            ),
            'provider_destructor_exception_debug' => fn () => ResultFactory::withProviderDestructorExceptionDebug(
                ResultFactory::ok(),
                $ex('Outer', 0, $ex('Destructor failure'))
            ),
            'provider_job_debug' => fn () => ResultFactory::withProviderJobDebug(ResultFactory::ok(), F::job('create')),
            'provider_output_debug' => fn () => ResultFactory::withProviderOutputDebug(
                ResultFactory::ok(),
                ['a' => 'b/c']
            ),
            'provider_output_debug_data_set' => fn () => ResultFactory::withProviderOutputDebug(
                ResultFactory::ok(),
                ResultData::create(['a' => 1], false)
            ),
            'execution_time_debug' => fn () => ResultFactory::withExecutionTimeDebug(ResultFactory::ok(), 1.23456),
            'validation_exception' => fn () => ResultFactory::fromProviderException(
                F::fixException(F::validationException(['name' => ['Name is required']]))
            ),
            'result_data' => fn () => ResultData::create(['a' => 1, 'b' => ['c' => 'd']], false)
                ->setMessage('done')
                ->setDebug(['d' => 1])
                ->toProvisionResult(),
            'result_data_defaults' => fn () => ResultFactory::fromResultData(ResultData::create([], false)),
            'function_error' => fn () => (new ProvisionFunctionError('Provider said no'))
                ->withData(['reason' => 'x'])
                ->withDebug(['response' => ['code' => 500]])
                ->toProvisionResult(),
            'function_error_defaults' => fn () => ResultFactory::fromFunctionError(new ProvisionFunctionError('')),
            'job_error_flow' => function () use ($ex): Result {
                $e = F::fixException(InvalidFunctionParameterDataException::fromInvalidDataSet(
                    new InvalidDataSetException(
                        F::fixException(F::validationException(['name' => ['Name is required']]))
                    )
                ), F::providerTrace());
                $e->setErrorPrefix('parameters');

                $result = ResultFactory::error($e->getMessage(), ['validation_errors' => $e->errors()]);
                $result = ResultFactory::withProviderOutputDebug($result, null);
                $result = ResultFactory::withProviderExceptionDebug($result, $e);
                $result = ResultFactory::withProviderJobDebug($result, F::job('create'));
                $result = ResultFactory::withProviderDestructorExceptionDebug($result, $ex('Destructor failure'));

                return ResultFactory::withExecutionTimeDebug($result, 0.0004);
            },
        ];
    }
}
