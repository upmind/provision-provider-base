<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Validation;

/**
 * Loads the validation conformance cases in tests/conformance/validation.
 */
final class ConformanceCases
{
    public const DIRECTORY = __DIR__ . '/../../../conformance/validation';

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
     * Get every case, keyed by a readable label.
     *
     * @return iterable<string,array{0:string,1:array<string,mixed>}>
     */
    public static function cases(): iterable
    {
        foreach (self::files() as $file) {
            foreach ($file['cases'] as $index => $case) {
                yield sprintf('%s #%d: %s', $file['rule'], $index, $case['name']) => [$file['rule'], $case];
            }
        }
    }
}
