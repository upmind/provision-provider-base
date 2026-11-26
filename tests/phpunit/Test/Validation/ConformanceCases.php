<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Validation;

/**
 * Loads the validation conformance cases of the package
 * upmind/provision-validation-conformance.
 */
final class ConformanceCases
{
    public const PACKAGE = __DIR__ . '/../../../../vendor/upmind/provision-validation-conformance';

    public const DIRECTORY = self::PACKAGE . '/validation';

    /**
     * Get rules.json: the supported rules and their aliases.
     *
     * @return array<string,mixed>
     */
    public static function rules(): array
    {
        return json_decode((string)file_get_contents(self::PACKAGE . '/rules.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Get the case files, keyed by rule name.
     *
     * @return array<string,array{rule:string,cases:array<int,array<string,mixed>>}>
     */
    public static function files(): array
    {
        $files = [];

        foreach (glob(self::DIRECTORY . '/*.json') ?: [] as $path) {
            $file = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $files[basename($path, '.json')] = $file;
        }

        ksort($files);

        return $files;
    }

    /**
     * Get every case, keyed by rule and case name.
     *
     * @return iterable<string,array{0:string,1:array<string,mixed>}>
     */
    public static function cases(): iterable
    {
        foreach (self::files() as $file) {
            foreach ($file['cases'] as $case) {
                yield sprintf('%s: %s', $file['rule'], $case['name']) => [$file['rule'], $case];
            }
        }
    }
}
