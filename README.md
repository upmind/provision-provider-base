# Upmind Provision Provider Base

[![Latest Version on Packagist](https://img.shields.io/packagist/v/upmind/provision-provider-base.svg?style=flat-square)](https://packagist.org/packages/upmind/provision-provider-base)

**To see an example of this library in action, check out [upmind/provision-workbench](https://github.com/upmind-automation/provision-workbench#readme)**

This library contains all the base interfaces, classes and logic to create
provision category and provider classes, and register them for use in a laravel
application.

Normally this library will not be used standalone, as it will be installed as a
sub-dependency of a provision category/provider library such as
[upmind/provision-provider-shared-hosting](https://github.com/upmind-automation/provision-provider-shared-hosting#readme).

- [Installation](#installation)
  - [Docker](#docker)
- [Usage](#usage)
  - [Categories](#create-a-category)
      - [Example Category](#example-category)
  - [Providers](#create-a-provider)
      - [Example Provider](#example-provider)
  - [Provision Registry](#registering-categories-and-providers)
      - [Example Service Provider](#example-service-provider)
      - [Using the Provision Registry](#using-the-provision-registry)
      - [Caching the Provision Registry](#caching-the-provision-registry)
  - [Executing Functions](#executing-provision-functions)
- [Data Sets](#data-sets)
  - [Example Parameter Data Set](#example-parameter-data-set)
  - [Example Return Data Set](#example-return-data-set)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [Credits](#credits)
- [License](#license)
- [Upmind](#upmind)

## Installation

```bash
composer require upmind/provision-provider-base
```

### Docker

The package provides samples of Dockerfile files to support local development and testing.

To use them, you need to have Docker and Docker Compose installed on your machine.

A `Makefile` is provided to simplify the usage of the docker-compose files.
  - `make setup-php82` sets up the PHP 8.2 development environment.
  - `make static-analysis` runs the static analysis.

## Usage

The sections below describe how to create Provision Categories and Provider
classes. Very simply, a provision [Category](#create-a-category) is an abstract
class which declares provision functions as abstract public methods. [Provider](#create-a-provider)
classes extend their parent Category class, and must therefore implement each
abstract provision function declared for the Category.

This library makes use of self-validating DataSets, which are explained [below](#data-sets).

### Create a Category

Provision categories are abstract classes which MUST implement [CategoryInterface](./src/Provider/Contract/CategoryInterface.php).
For convenience, a [BaseCategory](./src/Provider/BaseCategory.php) exists which can
be extended so that all Providers will have access to some common methods which
aid in producing provision function results.

Category classes act as the provision 'contract' and are responsible for 2 key
things:
1. Defining [AboutData](./src/Provider/DataSet/AboutData.php) which contains
the human-readable name and description and other metadata about the
category, by implementing [CategoryInterface::aboutCategory()](./src/Provider/Contract/CategoryInterface.php).
2. Defining abstract public methods which make up the available provision
functions of this category. These abstract methods should define their
parameters by type-hinting a single [DataSet](#data-sets)
class and their return values by return-typing a [ResultData](./src/Provider/DataSet/ResultData.php)
class, if parameters or return values are needed for any given function.

##### Example Category

```php
<?php

declare(strict_types=1);

namespace Upmind\ProvisionExample\Category\HelloWorld;

use Upmind\ProvisionBase\Provider\BaseCategory;
use Upmind\ProvisionBase\Provider\DataSet\AboutData;
use Upmind\ProvisionExample\Category\HelloWorld\Data\Greeting;
use Upmind\ProvisionExample\Category\HelloWorld\Data\PersonData;

/**
 * A simple category for 'Hello World' provider implementations. All Providers
 * of this Category must implement the 'greeting' function, using the single
 * parameter 'name'.
 */
abstract class HelloWorldCategory extends BaseCategory
{
    public static function aboutCategory(): AboutData
    {
        return AboutData::create()
            ->setName('Hello World')
            ->setDescription('A demonstration category that doesn\'t actually do anything');
    }

    /**
     * Greet the user by name.
     */
    abstract public function greeting(PersonData $person): Greeting;
}
```

### Create a Provider

Provision providers are classes which MUST extend their parent category class,
and also MUST implement [ProviderInterface](./src/Provider/Contract/ProviderInterface.php).
Since each provider in a category abides by the same 'contract', they receive
and must return the same data according to the DataSet type/return hints
defined in the abstract category functions.

Each provider represents a different implementation of a category and may
therefore require different data to configure them, such as API credentials etc.
This is supported by means of the provider class constructor, where the
required configuration data is defined by type-hinting a single DataSet
parameter.

Provider classes are the instantiable class implementations of provision
categories and are responsible for 3 main things:
1. Defining [AboutData](./src/Provider/DataSet/AboutData.php) which contains the
human-readable name and description and other metadata about the provider, by
implementing [ProviderInterface::aboutProvider()](./src/Provider/Contract/ProviderInterface.php).
2. Optionally defining a constructor which type-hints the DataSet required to
'configure' (instantiate) instances of the class.
3. Implementing each provision function defined by the category

##### Example Provider

```php
<?php

declare(strict_types=1);

namespace Upmind\ProvisionExample\Category\HelloWorld;

use Upmind\ProvisionBase\Provider\Contract\ProviderInterface;
use Upmind\ProvisionBase\Provider\DataSet\AboutData;
use Upmind\ProvisionExample\Category\HelloWorld\Data\FooConfiguration;
use Upmind\ProvisionExample\Category\HelloWorld\Data\PersonData;
use Upmind\ProvisionExample\Category\HelloWorld\Data\Greeting;

/**
 * This HelloWorld Provider 'Foo' provides its own implementation of the
 * 'greeting' provision function.
 */
class ProviderFoo extends HelloWorldCategory implements ProviderInterface
{
    /**
     * @var FooConfiguration
     */
    protected $configuration;

    /**
     * Providers always receive their configuration passed to their constructor.
     *
     * @param array $configuration
     */
    public function __construct(FooConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }

    public static function aboutProvider(): AboutData
    {
        return AboutData::create()
            ->setName('Hello Foo')
            ->setDescription('A demonstration of a provision function success case');
    }

    public function greeting(PersonData $person): Greeting
    {
        $apiKey = $this->configuration->api_key;
        $apiSecret = $this->configuration->api_secret;

        // $this->authenticateWithSomeApi($api_key, $api_secret);
        // do something with configuration data

        return Greeting::create()
            ->setMessage('Greeting generated successfully')
            ->setSentence(sprintf('Hello %s! From your friend, Foo.', $person->name));
    }
}
```

### Registering Categories and Providers

Categories and Providers are registered in a laravel application in the boot()
method of a [Service Provider](https://laravel.com/docs/master/providers).
Your service provider should extend this library's [ProvisionServiceProvider](./src/Laravel/ProvisionServiceProvider.php)
which gives it access to the 2 methods `bindCategory()` and `bindProvider()`.
Each provision category registered in an application must have a unique
identifier specified in the first argument of bindCategory(), and each provider
must have a unique identifier within their category specified in the second
argument of bindProvider().

##### Example Service Provider

```php
<?php

declare(strict_types=1);

namespace Upmind\ProvisionExample\Category\HelloWorld\Laravel;

use Upmind\ProvisionBase\Laravel\ProvisionServiceProvider;
use Upmind\ProvisionExample\Category\HelloWorld\HelloWorldCategory;
use Upmind\ProvisionExample\Category\HelloWorld\ProviderFoo;
use Upmind\ProvisionExample\Category\HelloWorld\ProviderBar;

class ServiceProvider extends ProvisionServiceProvider
{
    /**
     * Bind the HelloWorld Category and its Providers.
     */
    public function boot()
    {
        $this->bindCategory('hello-world', HelloWorldCategory::class);

        $this->bindProvider('hello-world', 'foo', ProviderFoo::class);
        $this->bindProvider('hello-world', 'bar', ProviderBar::class);
    }
}
```

#### Using the Provision Registry

The provision [Registry](./src/Registry/Registry.php) uses reflection to inspect
and verify each category and provider registration, enumerate their AboutData
and available provision functions and respective data sets and validation rules.

Using the above HelloWorld category example, the below code snippet shows how
to obtain the validation rules of the "greeting" function's parameter and return
data sets:

```php
<?php

$registry = Upmind\ProvisionBase\Registry\Registry::getInstance();

$greetingRegister = $registry->getCategory('hello-world')
    ->getFunction('greeting');
// => Instance of Upmind\ProvisionBase\Registry\Data\FunctionRegister

$parameterRules = $greetingRegister->getParameter()
    ->getRules()
    ->expand();
// => Array of portable laravel validation rules:
// [
//     'name' => [
//         'required',
//         'string'
//     ]
// ]

$returnRules = $greetingRegister->getReturn()
    ->getRules()
    ->expand();
// => Array of portable laravel validation rules:
// [
//     'sentence' => [
//         'required',
//         'string'
//     ]
// ]
```

Using the above Foo provider example, the below code snippet shows how to obtain
the validation rules of the provider's constructor data set:

```php
<?php

use Upmind\ProvisionBase\Registry\Registry;

$registry = Upmind\ProvisionBase\Registry\Registry::getInstance();

$fooRegister = $registry->getCategory('hello-world')
    ->getProvider('foo');
// => Instance of Upmind\ProvisionBase\Registry\Data\ProviderRegister

$fooConfigurationRules = $fooRegister->getConstructor()
    ->getParameter()
    ->getRules()
    ->expand();
// => Array of portable laravel validation rules:
// [
//     'api_key' => [
//         'required',
//         'string'
//     ],
//     'api_secret' => [
//         'required',
//         'string'
//     ]
// ]
```

#### Caching the Provision Registry

Since the operations required to verify/enumerate each category and provider
are relatively expensive, its possible to cache the registry to reduce overhead.

If the registry is cached, attempts to register new categories/providers will
instead be transparently pushed to a buffer so that this doesnt impact the
performance of the laravel application.

When caching the registry, buffered registers will then be evaluated, verified
and enumerated before the serialized form of the registry is returned.

See the below example of how to cache the registry in a laravel application,
which could be implemented as an artisan command for example:

```php
use Illuminate\Support\Facades\Cache;
use Upmind\ProvisionBase\Registry\Registry;

$cacheKey = 'upmind-provision-registry';
$serializedRegistry = serialize(Registry::getInstance());

Cache::forever($cacheKey, $serializedRegistry);
```

See the below example of how to resolve the registry from cache and bind it
to the service container as a singleton in a laravel service provider:

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Cache;
use Upmind\ProvisionBase\Registry\Registry;

/**
 * Bind the Provision Registry to the container
 */
class ProvisionRegistryServiceProvider extends ServiceProvider
{
    /**
     * @var string
     */
    public const REGISTRY_CACHE_KEY = 'upmind-provision-registry';

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Load the Provision Registry from cache, or obtain a fresh instance and bind
     * the Registry to the container.
     *
     * @return void
     */
    public function register()
    {
        // Attempt to set the Registry instance from cache
        if ($cachedRegistry = Cache::get(self::REGISTRY_CACHE_KEY)) {
            $registry = unserialize($cachedRegistry);

            if ($registry instanceof Registry) {
                Registry::setInstance($registry);
            }
        }

        // Bind registry as singleton to container
        $this->app->singleton(Registry::class, function () {
            return Registry::getInstance();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [Registry::class];
    }
}
```

### Executing Provision Functions

Some convenience classes are provided to ease the instantiation of provider
objects, and execute provision functions in exchange for a normalized provision
[Result](./src/Result/Result.php) object.

A [Factory](./src/ProviderFactory.php) is available to create a [Provider](./src/Provider.php)
instance wrapper, which can then create a [ProviderJob](./src/ProviderJob.php)
object which encapsulates the execution of a provision function.

See the below example for the Foo provider:

```php
<?php

use Upmind\ProvisionBase\ProviderFactory;
use Upmind\ProvisionBase\Registry\Registry;

$fooConfiguration = [
    'api_key' => 'foo api key here',
    'api_secret' => 'foo api secret here',
];

$factory = new ProviderFactory(Registry::getInstance());
$foo = $factory->create('hello-world', 'foo', $fooConfiguration);
// => Instance of Upmind\ProvisionBase\Provider

$greetingParameters = [
    'name' => 'Harry',
];
$greeting = $foo->makeJob('greeting', $greetingParameters);
// => Instance of Upmind\ProvisionBase\ProviderJob

$greetingResult = $greeting->execute();
// => Instance of Upmind\ProvisionBase\Result\Result

$data = $greetingResult->getData();
// Greeting result data:
// [
//     'sentence' => 'Hello Harry! From your friend, Foo.'
// ]
```

#### Results

A [Result](./src/Result/Result.php) is an immutable object with a status (`ok`
or `error`), a message, data and debug data. JSON-encode it to get the
provision result output.

Use [ResultFactory](./src/Result/ResultFactory.php) to create results. The
factory normalises the values and formats exceptions for debug data:

```php
<?php

use Upmind\ProvisionBase\Result\ResultFactory;

$result = ResultFactory::ok('Greeting created', ['sentence' => 'Hello Harry!']);
$result = ResultFactory::error('Greeting failed', errorId: 'error-id');
$result = ResultFactory::fromException($exception);
$result = ResultFactory::fromJson($json);
```

## Data Sets

Provision function parameter and return values, as well as provision provider
configuration values, are described and encapsulated within self-validating
data set objects.

These are classes which extend the base [DataSet](./src/Provider/DataSet/DataSet.php)
and must simply implement the abstract public static `rules()` method, returning
a [Rules](./src/Provider/DataSet/Rules.php) object.

Data sets validate themselves before any data can be returned from them, thus
ensuring that any given DataSet instance always contains valid data. If a
data set contains invalid data at the point which any values are attempted to be
taken from them, an [InvalidDataSetException](./src/Exception/InvalidDataSetException.php)
will be thrown, containing validation errors in the usual laravel format.

It is not necessary to call `->validate()` on data sets within your provider
code yourself, because this will be done in the ProviderFactory and ProviderJob
objects before the data sets are fed into your provider class/code.

See the example below defining PersonData which contains the parameters for the
HelloWorld::greeting() provision function. The same approach can be used to
define the structure of a provider's configuration data:

##### Example Parameter Data Set

```php
<?php

declare(strict_types=1);

namespace Upmind\ProvisionExample\Category\HelloWorld\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * Data set encapsulating a person.
 *
 * @property-read string $name Name of the person
 */
class PersonData extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'name' => ['required', 'string'],
        ]);
    }
}
```

For provision function return data, there is an alternative base [ResultData](./src/Provider/DataSet/ResultData.php)
class you can extend from which provides convenience methods for optionally
setting a [Result](./src/Result/Result.php) success message and/or debug
data which you can see in action in the Foo provider [above](#example-provider).

See the example below defining Greeting which contains the return data of the
HelloWorld::greeting() provision function:

##### Example Return Data Set

```php
<?php

declare(strict_types=1);

namespace Upmind\ProvisionExample\Category\HelloWorld\Data;

use Upmind\ProvisionBase\Provider\DataSet\ResultData;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * Data set encapsulating a greeting.
 *
 * @property-read string $sentence Greeting sentence
 */
class Greeting extends ResultData
{
    public static function rules(): Rules
    {
        return new Rules([
            'sentence' => ['required', 'string'],
        ]);
    }

    public function setSentence(string $sentence): self
    {
        $this->setValue('sentence', $sentence);
        return $this;
    }
}
```

## Changelog
Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.


## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

 - [Harry Lewis](https://github.com/uphlewis)
 - [Nikolai Arsov](https://github.com/nikiarsov777)
 - [Ivaylo Georgiev](https://github.com/Georgiev-Ivaylo)
 - [Roussetos Karafyllakis](https://github.com/RoussKS)
 - [Adam Quaile](https://github.com/adamquaile)
 - [All Contributors](../../contributors)

## License

GNU General Public License version 3 (GPLv3). Please see [License File](LICENSE.md) for more information.

## Upmind

Sell, manage and support web hosting, domain names, ssl certificates, website builders and more with [Upmind.com](https://upmind.com/start) - the ultimate web hosting billing and management solution.
