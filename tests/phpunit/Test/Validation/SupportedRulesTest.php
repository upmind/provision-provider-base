<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Validation;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Exception\UnsupportedValidationRule;
use Upmind\ProvisionBase\Laravel\Validation\SupportedRules;
use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\RuleParser;

class SupportedRulesTest extends TestCase
{
    protected function setUp(): void
    {
        // RuleParser asks the validator which strings are rules.
        DataSet::setValidatorFactory(new Factory(new Translator(new ArrayLoader(), 'en')));
    }

    protected function tearDown(): void
    {
        DataSet::setValidatorFactory(null);
    }

    public function testRulesEqualThePackageList(): void
    {
        $package = ConformanceCases::rules();
        $rules = SupportedRules::RULES;
        $supported = array_merge($package['supported']['laravel'], $package['supported']['custom']);
        sort($rules);
        sort($supported);

        $this->assertSame($supported, $rules);
        $this->assertSame($package['aliases'], SupportedRules::ALIASES);
    }

    public function testBlockedRulesAreNotSupported(): void
    {
        $this->assertSame([], array_intersect(array_keys(SupportedRules::BLOCKED), SupportedRules::RULES));
    }

    public function testEmailWithDnsParameterIsSupported(): void
    {
        $this->assertTrue(SupportedRules::isSupported('email:rfc,dns'));
    }

    public function testEverySupportedRuleHasCases(): void
    {
        $rules = SupportedRules::RULES;
        sort($rules);

        $this->assertSame($rules, array_keys(ConformanceCases::files()));
    }

    public function testCasesUseSupportedRules(): void
    {
        foreach (ConformanceCases::cases() as $label => [, $case]) {
            foreach ($case['rules'] as $fieldRules) {
                foreach ($fieldRules as $rule) {
                    $this->assertTrue(SupportedRules::isSupported($rule), "{$label}: rule {$rule} is not supported");
                }
            }
        }
    }

    public static function blockedRules(): array
    {
        $rules = array_map(function (string $rule): array {
            return [$rule];
        }, array_keys(SupportedRules::BLOCKED));

        return array_merge($rules, [['exists:users,id'], ['unique:users']]);
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
