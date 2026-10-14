<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Test\Form;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Laravel\Html\FormFactory;
use Upmind\ProvisionBase\Laravel\Html\FormField;
use Upmind\ProvisionBase\Laravel\Html\HtmlField;

class PasswordFieldTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // HtmlField parses rules through the Validator facade.
        $app = new Container();
        $app->instance('validator', new Factory(new Translator(new ArrayLoader(), 'en')));
        Facade::setFacadeApplication($app);
    }

    public static function tearDownAfterClass(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
    }

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
    public function testTypeShouldBePassword(string $name): void
    {
        $this->assertTrue(FormField::typeShouldBePassword($name, FormField::TYPE_INPUT_TEXT));
    }

    /**
     * @dataProvider nonPasswordNames
     */
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
    public function testFactoryCreatesPasswordField(string $name): void
    {
        $field = (new FormFactory())->createField($name, ['required', 'string']);

        $this->assertSame(FormField::TYPE_INPUT_PASSWORD, $field->type());
    }

    /**
     * @dataProvider nonPasswordNames
     */
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

    /**
     * @dataProvider passwordNames
     */
    public function testHtmlFieldCreatesPasswordField(string $name): void
    {
        $this->assertTrue(HtmlField::typeShouldBePassword($name, HtmlField::TYPE_INPUT_TEXT));
        $this->assertSame(
            HtmlField::TYPE_INPUT_PASSWORD,
            HtmlField::createFromValidationRules($name, ['required', 'string'])->type()
        );
    }

    /**
     * @dataProvider nonPasswordNames
     */
    public function testHtmlFieldCreatesTextField(string $name): void
    {
        $this->assertFalse(HtmlField::typeShouldBePassword($name, HtmlField::TYPE_INPUT_TEXT));
        $this->assertSame(
            HtmlField::TYPE_INPUT_TEXT,
            HtmlField::createFromValidationRules($name, ['required', 'string'])->type()
        );
    }

    public function testHtmlFieldKeepsTextareaForPasswordName(): void
    {
        $field = HtmlField::createFromValidationRules('client_private_key', ['required', 'string', 'certificate_pem']);

        $this->assertSame(HtmlField::TYPE_TEXTAREA, $field->type());
    }
}
