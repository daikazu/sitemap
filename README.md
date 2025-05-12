<p align="center">
<img src="sitemap.png" alt="Sitemap Logo" style="max-height: 300px">
</p>
# Laravel Sitemap Generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/daikazu/sitemap.svg?style=flat-square)](https://packagist.org/packages/daikazu/sitemap)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/daikazu/sitemap/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/daikazu/sitemap/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/daikazu/sitemap/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/daikazu/sitemap/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/daikazu/sitemap.svg?style=flat-square)](https://packagist.org/packages/daikazu/sitemap)

A Laravel package for generating and managing XML sitemaps with caching and cooldown periods. This package provides an easy way to generate and maintain sitemaps for your Laravel applications while preventing excessive generation requests.

## Features

- Automatic sitemap generation with configurable cooldown periods
- Caching of sitemap content for improved performance
- Environment-aware generation (skips generation in local environment)
- Force regeneration capability when needed
- Built on top of the popular `spatie/laravel-sitemap` package
- Automatic scheduling of sitemap generation

## Installation

You can install the package via composer:

```bash
composer require daikazu/sitemap
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="sitemap-config"
```

This is the contents of the published config file:

```php
return [
    // The cooldown period in hours between sitemap generations
    'cooldown_hours' => env('SITEMAP_COOLDOWN_HOURS', 24),

    // Schedule settings for automatic sitemap generation
    'schedule' => [
        // Enable or disable scheduled generation
        'enabled' => env('SITEMAP_SCHEDULE_ENABLED', true),

        // Time of day to run the daily generation (24-hour format)
        'daily_time' => env('SITEMAP_DAILY_TIME', '00:00'),

        // Interval in minutes to check for cooldown expiration
        'check_interval' => env('SITEMAP_CHECK_INTERVAL', 1),
    ],
];
```

## Usage

The package provides a `SitemapService` that handles sitemap generation and caching:

```php
use Daikazu\Sitemap\Services\SitemapService;

$sitemapService = app(SitemapService::class);

// Generate sitemap if due (respects cooldown period)
$sitemapService->generateIfDue();

// Force regenerate sitemap regardless of cooldown
$sitemapService->forceRegenerate();

// Get the sitemap content
$sitemapContent = $sitemapService->getSitemapContent();
```

The package automatically handles scheduling of sitemap generation based on your configuration settings. You can customize the scheduling behavior through the config file.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Mike Wall](https://github.com/daikazu)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
