<a href="https://mikewall.dev/">
<picture>
  <source media="(prefers-color-scheme: dark)" srcset="art/header-dark.png">
  <img alt="Logo for daikazu/sitemap" src="art/header-light.png">
</picture>
</a>

# Laravel Sitemap Generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/daikazu/sitemap.svg?style=flat-square)](https://packagist.org/packages/daikazu/sitemap)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/daikazu/sitemap/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/daikazu/sitemap/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/daikazu/sitemap/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/daikazu/sitemap/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/daikazu/sitemap.svg?style=flat-square)](https://packagist.org/packages/daikazu/sitemap)

A Laravel package for generating and managing XML sitemaps with caching and cooldown periods. This package provides an easy way to generate and maintain sitemaps for your Laravel applications while preventing excessive generation requests.

## Features

- Automatic sitemap generation with configurable cooldown periods
- **Sitemap Index Support** - Split large sitemaps into multiple files (50,000 URLs per file)
- **Model-Based Generation** - Generate sitemaps from Eloquent models (faster, includes authenticated content)
- **Hybrid Mode** - Combine crawled URLs with model-based URLs
- Caching of sitemap content for improved performance
- Environment-aware generation (skips generation in local environment)
- Force regeneration capability when needed
- Built on top of the popular `spatie/laravel-sitemap` package
- Automatic scheduling of sitemap generation
- Storage-based sitemap management for better control

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

    // Storage settings
    'storage' => [
        'disk' => env('SITEMAP_STORAGE_DISK', 'public'),
        'path' => env('SITEMAP_STORAGE_PATH', 'sitemaps'),
        'filename' => env('SITEMAP_FILENAME', 'sitemap.xml'),
    ],

    // Sitemap Index settings (for large sites)
    'index' => [
        'enabled' => env('SITEMAP_INDEX_ENABLED', false),
        'max_urls_per_sitemap' => env('SITEMAP_MAX_URLS', 50000),
        'filename_pattern' => env('SITEMAP_FILENAME_PATTERN', 'sitemap-%d.xml'),
        'index_filename' => env('SITEMAP_INDEX_FILENAME', 'sitemap.xml'),
    ],

    // Schedule settings for automatic sitemap generation
    'schedule' => [
        'enabled' => env('SITEMAP_SCHEDULE_ENABLED', true),
        'daily_time' => env('SITEMAP_DAILY_TIME', '00:00'),
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

### Sitemap Index (Multi-Sitemap) Support

For large sites with more than 50,000 URLs, enable sitemap index support:

```php
// In config/sitemap.php
'index' => [
    'enabled' => true,
    'max_urls_per_sitemap' => 50000,
    'filename_pattern' => 'sitemap-%d.xml',
    'index_filename' => 'sitemap.xml',
],
```

When enabled, the package will:
- Split your sitemap into multiple files (sitemap-1.xml, sitemap-2.xml, etc.)
- Generate a sitemap index (sitemap.xml) that references all individual sitemaps
- Automatically serve the correct sitemap based on the requested URL

The main sitemap index will be available at `/sitemap.xml`, and individual sitemaps at `/sitemaps/sitemap-1.xml`, `/sitemaps/sitemap-2.xml`, etc.

### Model-Based Sitemap Generation

Generate sitemaps directly from your Eloquent models - much faster than crawling and includes dynamic/authenticated content:

```php
// In config/sitemap.php
'models' => [
    \App\Models\Post::class => [
        'enabled' => true,
        'url' => fn($post) => route('posts.show', $post->slug),
        'lastmod' => 'updated_at', // column name or closure
        'changefreq' => 'weekly',
        'priority' => 0.8, // or use closure: fn($post) => $post->is_featured ? 0.9 : 0.7
        'query' => fn($query) => $query->where('published', true),
    ],
    \App\Models\Product::class => [
        'enabled' => true,
        'url' => fn($product) => route('products.show', $product),
        'lastmod' => fn($product) => $product->updated_at,
        'priority' => fn($product) => $product->in_stock ? 0.8 : 0.5,
    ],
],

// Set generation mode
'generate_mode' => 'hybrid', // Options: 'crawl', 'models', or 'hybrid'
```

**Generation Modes:**
- `crawl` - Traditional website crawling (default)
- `models` - Only generate from configured models
- `hybrid` - Combine both crawled pages and model-based URLs (with automatic deduplication)

**Commands:**
```bash
# Generate sitemap with configured mode (crawl/models/hybrid)
php artisan app:generate-sitemap

# Generate sitemap from models only (ignores generate_mode)
php artisan app:generate-model-sitemap
```

**Hybrid Mode Deduplication:**
When using hybrid mode, the package intelligently prevents duplicate URLs by:
- Tracking all model-generated URLs
- Normalizing URLs (removes trailing slashes, tracking parameters, case differences)
- Skipping crawled URLs that were already added from models
- Result: Clean sitemap with no duplicates, combining the best of both approaches!

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
