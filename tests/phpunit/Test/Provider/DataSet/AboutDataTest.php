<?php

declare(strict_types=1);

namespace Unit\Provider\DataSet;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Upmind\ProvisionBase\Exception\InvalidDataSetException;
use Upmind\ProvisionBase\Provider\DataSet\AboutData;

class AboutDataTest extends TestCase
{
    private MockObject&Factory $validatorFactory;

    protected function setUp(): void
    {
        $this->validatorFactory = $this->createMock(Factory::class);
    }

    /**
     * This is an example test, performed to confirm decoupling from Laravel's Validator Facade.
     * It asserts that an InvalidDataSetException is thrown when validation fails.
     *
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    public function test_invalid_data_set_exception_thrown_on_validation_failure(): void
    {
        $this->expectException(InvalidDataSetException::class);

        $translator = $this->createMock(Translator::class);
        $validator = $this->createMock(Validator::class);

        $messageBag = new MessageBag();
        $messageBag->add('name', 'The name field is required.');

        $validator->method('getTranslator')->willReturn($translator);
        $validator->method('errors')->willReturn($messageBag);
        $validator->method('validate')->willThrowException(new ValidationException($validator));

        $this->validatorFactory->method('make')->willReturn($validator);

        AboutData::setValidatorFactory($this->validatorFactory);

        $aboutData = new AboutData();

        $aboutData->validate();
    }
}
