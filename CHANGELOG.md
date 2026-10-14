# Changelog

All notable changes to the package will be documented in this file.

## Unreleased

- Remove Guzzle stack history & request debug data (which use history)
- Update FormField::typeShouldBePassword() to match private key and passphrase
  field names, e.g., `client_private_key` and `key_passphrase`
  - Text fields with these names now render as `input_password`

## v4.0.1 - 2024-02-27

- Fix RuleParser issue with Rule expected to be a string, while a class or callback
  might be provided, when a Laravel application is loaded

## v4.0 - 2024-02-13

- Update minimum PHP version to 8.1 and minimum Laravel version to 10.0
  - Update dependencies accordingly
  - Improve various return types + constructor safety accordingly
- Remove Provider Storage filesystem adapter + related code
- Remove deprecated HtmlField + related code
- Deprecate AttributeQuery classes

## v3.7.1 - 2024-02-27

- Fix RuleParser issue with Rule expected to be a string, while a class or callback
  might be provided, when a Laravel application is loaded

## v3.7.0 - 2023-04-05

- Add SystemInfo dataset to contain system/environment/runtime metadata e.g., `outgoing_ips`
  and inject into all Provider instances in ProviderFactory (closes #8)

## v3.6.1 - 2023-02-24

- Update OptionData rules allow empty label

## v3.6 - 2023-02-24

- Add new Helper methods
  - Helper::buildUrl()
  - Helper::urlAppendQuery()

## v3.5.3 - 2023-02-24

- Update OptionData rules allow empty label

## v3.5.2 - 2022-11-09

- Add missing deprecation flag to Rules::toHtmlFields()

## v3.5.1 - 2022-11-09

- Remove unnecessary FormFactory::createField() strtolower() to fix issues with
  case sensitive `in:` rule arguments

## v3.5.0 - 2022-11-08

- Update RuleParser
  - Fix parseRule() returned arguments when rule arguments are missing
  - Add new methods including getRuleArguments(), containsAnyRule() and
    getFieldPrefix()
  - Update filterNestedItems() to support optionally also returning the rules of
    the parent
- Add Html Form classes which improve the semantic HTML field representation of
  more complicated validation rule sets such as those containing variable length
  arrays or nested/multi-assoc fields
  - FormFactory
  - Form
  - FormElement
  - FormGroup
  - FormField
- Deprecate HtmlField

## v3.4.0 - 2022-10-21

Update BaseCategory Guzzle stack
  - Record a history of requests/responses in $this->guzzleHistory
  - Add getLastGuzzleRequestDebug() which returns an array of debug information
    about the most recent request + response

## v3.3.0 - 2022-10-20

- Add Helper::generateStrictPassword() for generating passwords adhering to strict
  rules

## v3.2.0 - 2022-10-18

- Widen guzzlehttp/guzzle version constraint to allow v7

## v3.1.1 - 2022-10-17

- Fix DataSet\\Rules::toHtmlFields() to not return fields for NESTED_DATA_SET_RULE
  keys

## v3.1.0 - 2022-10-15

- Update ProviderInterface to extend LogsDebugData so that a logger instance is
  always injected to every Provider

## v3.0.0 - 2022-10-14

### New
- Add console commands for inspecting and caching the provision registry
- Add HtmlField class for representing a set of validation rules by an input form
  field
- Add method to conveniently convert a DataSet\Rules instance to an array of HtmlField
  instances
- Add optional `icon` and `logo_url` values to Provider + Category AboutData
- Update ProvisionResult to add support for destructor exception debug data
- Add getter and setter methods for Logger and Filesystem in ProviderFactory
- Various minor bugfixes and convenience methods added to internal classes

### Changed (Breaking)
- Move custom validation rule classes to their own sub-directory/namespace
- Explicitly unset Provider instance when executing a ProviderJob to immediately
  trigger destructors
- Changes to various internal class method parameter types

## v2.1.3 - 2022-09-23

- Fix spelling error in Upmind\ProvisionBase\Provider\Helper\Api\Response::getDefaultMessage()

## v2.1.2 - 2022-08-26

- Update ProviderJob always return result with `execution_time` debug

## v2.1.1 - 2022-08-12

- Implement international_phone validation rule workaround for new
  Nigeria 091 phone number format

## v2.1 - 2022-08-05

- Add new `certificate_pem` validation rule

## v2.0.5 - 2022-07-25

- Prevent type errors resulting from passing non-string values to preg_match in
  ValidationServiceProvider

## v2.0.4 - 2022-07-13

- FIX (Issue #2): Implement international_phone validation rule workaround for
  new Zimbabwe, Ivory Coast & Morocco phone number formats

## v2.0.3 - 2022-07-06

- FIX (Issue #1): Make Registry serialization compatible with PHP 7.4

## v2.0.2 - 2022-07-05

- Explicitly require `psr/log:^1.1`

## v2.0.1 - 2022-04-29

- FIX: Bind provision Registry as a singleton in ProvisionServiceProvider

## v2.0 - 2022-04-29

Initial public release
