<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Laravel;

use Exception;
use Illuminate\Validation\Validator as LaravelValidator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\ServiceProvider as BaseProvider;
use Illuminate\Support\Facades\Validator;
use League\ISO3166\ISO3166;
use libphonenumber\NumberParseException;
use Propaganistas\LaravelPhone\Exceptions\NumberParseException as PropaganistasNumberParseException;
use Upmind\ProvisionBase\Laravel\Validation\Rules\CertificatePem;
use Upmind\ProvisionBase\Provider\DataSet\RuleParser;
use Upmind\ProvisionBase\Provider\DataSet\Validator as DataSetValidator;

/**
 * Provides additional validation rules useful for Providers
 */
class ValidationServiceProvider extends BaseProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->bootCustomRules();

        $this->bootDataSetValidatorResolver();
    }

    protected function bootCustomRules(): void
    {
        $this->bootAttributeQueryRule();

        $this->bootAlphaScoreRule();

        $this->bootAlphaDashDotRule();

        $this->bootDomainNameRule();

        $this->bootInternationalPhoneRule();

        $this->bootCountryCodeRule();

        $this->bootStepRule();

        $this->bootCertificatePemRule();
    }

    protected function bootDataSetValidatorResolver()
    {
        /** @var \Illuminate\Validation\Factory $factory */
        $factory = Validator::getFacadeRoot();
        $factory->resolver(function ($translator, $data, $rules, $messages, $customAttributes) use ($factory) {
            if (RuleParser::containsRule($rules, RuleParser::NESTED_DATA_SET_RULE)) {
                return new DataSetValidator($factory, $translator, $data, $rules, $messages, $customAttributes);
            }

            return new LaravelValidator($translator, $data, $rules, $messages, $customAttributes);
        });
    }

    protected function bootAttributeQueryRule()
    {
        Validator::extend(
            AttributeQueryValidator::RULE_NAME,
            'Upmind\ProvisionBase\Laravel\AttributeQueryValidator@validateAttributeQuery'
        );
    }

    protected function bootAlphaScoreRule()
    {
        $extension = function ($attribute, $value, $parameters, $validator) {
            return !preg_match('/[^\w]/', strval($value));
        };
        $message = 'This value must only contain letters, numbers and underscores.';

        Validator::extend('alpha_score', $extension, $message);
        Validator::extend('alpha-score', $extension, $message);
    }

    protected function bootAlphaDashDotRule()
    {
        $extension = function ($attribute, $value, $parameters, $validator) {
            return !preg_match('/[^\w\-\.]/', strval($value));
        };
        $message = 'This value must only contain letters, numbers, dashes, underscores and periods.';

        Validator::extend('alpha_dash_dot', $extension, $message);
        Validator::extend('alpha-dash-dot', $extension, $message);
    }

    protected function bootDomainNameRule()
    {
        $extension = function ($attribute, $value, $parameters, $validator) {
            /** @link https://stackoverflow.com/a/4694816/4741456 */
            return is_string($value)
                && preg_match('/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))+$/i', $value) //valid chars check
                && preg_match('/^.{3,253}$/', $value) //overall length check
                && preg_match('/^[^\.]{1,63}(\.[^\.]{1,63})*$/', $value); //length of each label
        };
        $message = 'This is not a valid domain name.';

        Validator::extend('domain-name', $extension, $message);
        Validator::extend('domain_name', $extension, $message);
    }

    /**
     * Provides the validation rule `international_phone` used to validate that phone number
     * inputs either conform to the provided country code (country code field specified
     * by argument to rule e.g., `international_phone:country_code`), or is in itself
     * a valid international phone number e.g., +44751587251 or +35921231234 from
     * which it is possible to parse out the international dialling code from the
     * rest of the phone number.
     */
    protected function bootInternationalPhoneRule()
    {
        /** @param LaravelValidator $validator */
        $extension = function ($attribute, $value, $parameters, $validator) {
            if (!empty($parameters[0])) {
                //validate phone number for the input country code
                $countryCode = Arr::get($validator->getData(), $parameters[0]);

                $extraValidator = Validator::make([
                    'phone' => $value
                ], [
                    'phone' => sprintf('phone:%s', $countryCode)
                ]);

                if ($extraValidator->fails()) {
                    if (!$this->manualCheckPhones($value)) {
                        // manually adding an error will cause validation to fail with this specific error message
                        $validator->errors()
                            ->add(
                                $attribute,
                                $this->makeReplacements('This is not a valid :COUNTRY_CODE phone number', [
                                    'attribute' => $attribute,
                                    'country_code' => $countryCode,
                                ])
                            );
                    }
                }

                return true;
            }

            //validate for a valid international phone number regardless of country

            if (!Str::startsWith($value, '+')) {
                // manually adding an error will cause validation to fail with this specific error message
                $validator->errors()
                    ->add(
                        $attribute,
                        $this->makeReplacements('The phone number must begin with +{dialing code}', [
                            'attribute' => $attribute
                        ])
                    );

                return true;
            }

            try {
                $phone = phone($value);
                $phone->getCountry();
            } catch (PropaganistasNumberParseException | NumberParseException $e) {
                return $this->manualCheckPhones($value);
            }

            return true;
        };

        $message = 'This is not a valid international phone number';

        Validator::extend('international_phone', $extension, $message);
    }

    protected function bootCountryCodeRule()
    {
        $extension = function ($attribute, $value, $parameters, $validator) {
            try {
                /**
                 * Accept "reserved" codes (deprecated / changed codes) which are not included in the ISO3166 library
                 *
                 * @link https://github.com/thephpleague/iso3166/issues/42
                 * @link https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2#Transitional_reservations
                 */
                $reservedCodes = [
                    'AN',
                    'BU',
                    'CS',
                    'NT',
                    'SF',
                    'TP',
                    'UK',
                    'YU',
                    'ZR',
                    'XK', // Kosovo
                ];
                if (in_array(strtoupper($value), $reservedCodes)) {
                    return true;
                }

                $data = (new ISO3166())->alpha2($value);

                return true;
            } catch (Exception $e) {
                return false;
            }
        };
        $message = 'This is not a valid country code.';

        Validator::extend('country_code', $extension, $message);
    }

    protected function bootStepRule(): void
    {
        Validator::extend('step', 'Upmind\ProvisionBase\Laravel\Validation\Rules\Step@validateStep');
        Validator::replacer('step', 'Upmind\ProvisionBase\Laravel\Validation\Rules\Step@replaceStep');
    }

    protected function bootCertificatePemRule(): void
    {
        Validator::extend(
            'certificate_pem',
            CertificatePem::class,
            'The :attribute must be a certificate in PEM format'
        );
    }

    /**
     * Make the place-holder replacements on a message line.
     *
     * @see \Illuminate\Translation\Translator::makeReplacements()
     *
     * @param  string  $line
     * @param  array   $replace
     *
     * @return string
     */
    protected function makeReplacements($line, array $replace)
    {
        if (empty($replace)) {
            return $line;
        }

        $replace = $this->sortReplacements($replace);

        foreach ($replace as $key => $value) {
            $line = str_replace(
                [':' . $key, ':' . Str::upper($key), ':' . Str::ucfirst($key)],
                [$value, Str::upper($value), Str::ucfirst($value)],
                $line
            );
        }

        return $line;
    }

    /**
     * Sort the replacements array.
     *
     * @see \Illuminate\Translation\Translator::sortReplacements()
     *
     * @param  array  $replace
     *
     * @return array
     */
    protected function sortReplacements(array $replace)
    {
        return (new Collection($replace))->sortBy(function ($value, $key) {
            return mb_strlen($key) * -1;
        })->all();
    }

    /**
     * @param $value
     * @return bool
     */
    private function manualCheckPhones($value): bool
    {
        $value = strval($value);

        //Morocco phone validation
        if (preg_match('/(\+212|0)\.?([ \-_\/]*)(\d[ \-_\/]*){9}/', $value)) {
            return true;
        }

        //Zimbabwe phone validation
        if (preg_match('/(\+263|0)\.?([ \-_\/]*)(\d[ \-_\/]*){9}/', $value)) {
            return true;
        }

        //Ivory Coast phone validation
        if (preg_match('/(\+225|0)\.?([ \-_\/]*)(\d[ \-_\/]*){10}/', $value)) {
            return true;
        }

        //Nigerian 91 Coast phone validation
        if (preg_match('/(\+234|0)91\.?([ \-_\/]*)(\d[ \-_\/]*){8}$/', $value)) {
            return true;
        }

        return false;
    }
}
