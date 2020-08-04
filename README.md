# Speed and SQL trap for Laravel Tests

[![PHP Version](https://img.shields.io/packagist/php-v/hughsaffar/laravel-test-trap)](https://packagist.org/packages/spatie/laravel-test-trap)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/hughsaffar/laravel-test-trap)](https://packagist.org/packages/hughsaffar/laravel-test-trap)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/hughsaffar/laravel-test-trap/Tests)](https://github.com/hughsaffar/laravel-test-trap/actions?query=workflow%3ATests+branch%3Amaster)
[![License](https://img.shields.io/packagist/l/hughsaffar/laravel-test-trap)](https://packagist.org/packages/hughsaffar/laravel-test-trap)

![Screenshot of terminal using Laravel Test Trap](https://i.imgur.com/a6hoowt.png)

Laravel Test Trap will help you trap any slow tests or when a SQL query is slow oOr run multiple times. 
Laravel Test Trap is inspired by [phpunit-speedtrap](https://github.com/johnkary/phpunit-speedtrap) and is tailored to be used in Laravel applications. 

## Installation

You can install the package via composer:

```bash
composer require --dev hughsaffar/laravel-test-trap
```

You can publish the config file with:
```bash
php artisan vendor:publish --provider="TestTrap\TestTrapServiceProvider" --tag="config"
```

This is the contents of the published config file:

```php
return [
  'environment_name' => env('TEST_TRAP_ENVIRONMENT', 'testing')
];
```

This config can be used if you name your test environment, something other than `testing`.

## Usage

Laravel Test Trap comes with an PHPUnit extension class that you need to add to your `phpunit.xml`.

``` xml
<extensions>
    <extension class="TestTrap\TestTrapExtension">
        <arguments>
            <array>
                <element key="speed">
                    <double>500</double>
                </element>
                <element key="queryCalled">
                    <integer>10</integer>
                </element>
                <element key="querySpeed">
                    <double>1000</double>
                </element>
            </array>
        </arguments>
    </extension>
</extensions>
```

In this example we ask Test Trap to report any tests that:

- Take more than 500ms to run
- Or run a single query more than 10 times in a test
- Or a query takes more than a second (1000ms) to run

## Testing

``` bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email hs@houmaan.ca instead of using the issue tracker.

## Credits

- [Hugh Saffar](https://github.com/hughsaffar)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
