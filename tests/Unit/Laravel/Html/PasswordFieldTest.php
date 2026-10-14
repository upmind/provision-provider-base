<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Tests\Unit\Laravel\Html;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Laravel\Html\FormFactory;
use Upmind\ProvisionBase\Laravel\Html\FormField;

class PasswordFieldTest extends TestCase
{
    public static function passwordNames(): array
    {
        return [
            ['password'],
            ['api_key'],
            ['client_private_key'],
            ['private-key'],
            ['privateKey'],
            ['PRIVATE_KEY'],
            ['key_passphrase'],
            ['client_private_key_passphrase'],
        ];
    }

    public static function nonPasswordNames(): array
    {
        return [
            ['username'],
            ['public_key'],
            ['key_type'],
            ['key_size'],
            ['keyword'],
        ];
    }

    /**
     * @dataProvider passwordNames
     */
    #[DataProvider('passwordNames')]
    public function testTypeShouldBePassword(string $name): void
    {
        $this->assertTrue(FormField::typeShouldBePassword($name, FormField::TYPE_INPUT_TEXT));
    }

    /**
     * @dataProvider nonPasswordNames
     */
    #[DataProvider('nonPasswordNames')]
    public function testTypeShouldNotBePassword(string $name): void
    {
        $this->assertFalse(FormField::typeShouldBePassword($name, FormField::TYPE_INPUT_TEXT));
    }

    public function testNonPasswordableTypeShouldNotBePassword(): void
    {
        $this->assertFalse(FormField::typeShouldBePassword('client_private_key', FormField::TYPE_TEXTAREA));
        $this->assertFalse(FormField::typeShouldBePassword('key_passphrase', FormField::TYPE_SELECT));
    }

    /**
     * @dataProvider passwordNames
     */
    #[DataProvider('passwordNames')]
    public function testFactoryCreatesPasswordField(string $name): void
    {
        $field = (new FormFactory())->createField($name, ['required', 'string']);

        $this->assertSame(FormField::TYPE_INPUT_PASSWORD, $field->type());
    }

    /**
     * @dataProvider nonPasswordNames
     */
    #[DataProvider('nonPasswordNames')]
    public function testFactoryCreatesTextField(string $name): void
    {
        $field = (new FormFactory())->createField($name, ['required', 'string']);

        $this->assertSame(FormField::TYPE_INPUT_TEXT, $field->type());
    }

    public function testFactoryKeepsTextareaForPasswordName(): void
    {
        $field = (new FormFactory())->createField('client_private_key', ['required', 'string', 'certificate_pem']);

        $this->assertSame(FormField::TYPE_TEXTAREA, $field->type());
    }
}
