<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Exception\UnsupportedValidationRule;
use Upmind\ProvisionBase\Laravel\Validation\SupportedRules;
use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\RuleParser;

class SupportedRulesTest extends TestCase
{
    public function testEverySupportedRuleHasCases(): void
    {
        $rules = SupportedRules::RULES;
        sort($rules);

        $this->assertSame($rules, array_keys(ConformanceCases::files()));
    }

    public function testCaseFilesAreWellFormed(): void
    {
        foreach (ConformanceCases::files() as $name => $file) {
            $this->assertSame($name, $file['rule'], "File {$name}.json has rule {$file['rule']}");
            $this->assertNotEmpty($file['cases'], "File {$name}.json has no cases");

            $valid = array_filter(array_column($file['cases'], 'valid'));
            $this->assertNotEmpty($valid, "File {$name}.json has no valid case");
            $this->assertNotCount(count($file['cases']), $valid, "File {$name}.json has no invalid case");

            foreach ($file['cases'] as $index => $case) {
                $label = "{$name}.json case #{$index}";

                $this->assertSame(['name', 'rules', 'data', 'valid'], array_slice(array_keys($case), 0, 4), $label);
                $this->assertIsString($case['name'], $label);
                $this->assertIsArray($case['data'], $label);
                $this->assertIsBool($case['valid'], $label);
                $this->assertSame(!$case['valid'], array_key_exists('failed', $case), "{$label}: failed is set only for invalid cases");
                $this->assertNotEmpty($case['rules'], $label);

                foreach ($case['rules'] as $field => $fieldRules) {
                    $this->assertIsString($field, $label);
                    $this->assertIsArray($fieldRules, "{$label}: rules of {$field} must be a list");

                    foreach ($fieldRules as $rule) {
                        $this->assertIsString($rule, $label);
                        $this->assertTrue(SupportedRules::isSupported($rule), "{$label}: rule {$rule} is not supported");
                    }
                }
            }
        }
    }

    public function testEveryCaseFileTestsItsRule(): void
    {
        foreach (ConformanceCases::files() as $name => $file) {
            $rules = [];

            foreach ($file['cases'] as $case) {
                foreach ($case['rules'] as $fieldRules) {
                    foreach ($fieldRules as $rule) {
                        $rules[] = SupportedRules::name($rule);
                    }
                }
            }

            $this->assertContains($name, $rules, "File {$name}.json does not use rule {$name}");
        }
    }

    public static function blockedRules(): array
    {
        $rules = array_map(function (string $rule): array {
            return [$rule];
        }, array_keys(SupportedRules::BLOCKED));

        return array_merge($rules, [['email:rfc,dns'], ['exists:users,id'], ['unique:users']]);
    }

    /**
     * @dataProvider blockedRules
     */
    #[DataProvider('blockedRules')]
    public function testBlockedRulesThrow(string $rule): void
    {
        $this->expectException(UnsupportedValidationRule::class);
        $this->expectExceptionMessage('is blocked');

        SupportedRules::assertSupported($rule);
    }

    public function testUnknownRuleThrows(): void
    {
        $this->expectException(UnsupportedValidationRule::class);
        $this->expectExceptionMessage('Validation rule not_a_rule is not supported');

        SupportedRules::assertSupported('not_a_rule');
    }

    public function testRuleNamesAreNormalised(): void
    {
        $this->assertSame('domain_name', SupportedRules::name('domain-name'));
        $this->assertSame('required_if', SupportedRules::name('Required_If:a,b'));
        $this->assertSame('alpha_dash_dot', SupportedRules::name(' alpha-dash-dot '));
    }

    public function testRuleParserRejectsUnsupportedRules(): void
    {
        $this->expectException(UnsupportedValidationRule::class);

        RuleParser::expand(['name' => ['required', 'unique:users']]);
    }

    public function testPackageDataSetsUseSupportedRules(): void
    {
        $classes = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 4) . '/src')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen(dirname(__DIR__, 4) . '/src/'));
            $class = 'Upmind\\ProvisionBase\\' . str_replace(['/', '.php'], ['\\', ''], $relative);

            if (is_subclass_of($class, DataSet::class) && !(new \ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }

        $this->assertNotEmpty($classes);

        foreach ($classes as $class) {
            $class::rules()->expand();
        }

        $this->addToAssertionCount(count($classes));
    }
}
