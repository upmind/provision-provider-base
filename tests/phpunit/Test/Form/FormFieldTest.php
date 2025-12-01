<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Form;

use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Laravel\Html\FormField;
use Upmind\ProvisionBase\Provider\DataSet\RuleParser;

class FormFieldTest extends TestCase
{
    protected function setUp(): void
    {
        RuleParser::setValidator($this->createMock(\Illuminate\Validation\Validator::class));
    }

    public function testOptionValuesAreCastToString(): void
    {
        $options = [
            1 => 'Option One',
            2 => 'Option Two',
            3 => 'Option Three',
        ];

        $normalizedOptions = FormField::normalizeOptions($options);
        foreach ($normalizedOptions as $option) {
            $option->autoValidation(false);
        }

        $this->assertSame('1', $normalizedOptions[0]['value']);
        $this->assertSame('2', $normalizedOptions[1]['value']);
        $this->assertSame('3', $normalizedOptions[2]['value']);
    }
}
