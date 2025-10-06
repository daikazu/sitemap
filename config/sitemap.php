<?php

// config for Daikazu/Sitemap
return [
    /*
    |--------------------------------------------------------------------------
    | Sitemap Generation Settings
    |--------------------------------------------------------------------------
    |
    | Configure the cooldown period and schedule times for sitemap generation.
    |
    */

    // The cooldown period in hours between sitemap generations
    'cooldown_hours' => env('SITEMAP_COOLDOWN_HOURS', 24),

    // Storage settings
    'storage' => [
        // The disk where sitemaps will be stored
        'disk' => env('SITEMAP_STORAGE_DISK', 'public'),

        // The path within the disk where sitemaps will be stored
        'path' => env('SITEMAP_STORAGE_PATH', 'sitemaps'),

        // The filename for the sitemap
        'filename' => env('SITEMAP_FILENAME', 'sitemap.xml'),
    ],

    // Schedule settings for automatic sitemap generation
    'schedule' => [
        // Enable or disable scheduled generation
        'enabled' => env('SITEMAP_SCHEDULE_ENABLED', true),

        // Time of day to run the daily generation (24-hour format)
        'daily_time' => env('SITEMAP_DAILY_TIME', '00:00'),
    ],

    // Skipped common none-content URLs
    'skip_patterns' => [
        '/login', '/logout', '/register', '/admin', '/cart', '/checkout',
        'wp-admin', 'wp-login', 'wp-content',
        '/feed/', '/search',
        '.jpg', '.jpeg', '.png', '.gif', '.css', '.js', '.pdf', '.zip',
        '/blog/category/', '/blog/tag/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sitemap Index Settings
    |--------------------------------------------------------------------------
    |
    | Configure multi-sitemap support for large sites. When enabled, the
    | sitemap will be split into multiple files with an index.
    |
    */

    'index' => [
        // Enable sitemap index (split into multiple sitemaps)
        'enabled' => env('SITEMAP_INDEX_ENABLED', false),

        // Maximum URLs per sitemap file (Google recommends max 50,000)
        'max_urls_per_sitemap' => env('SITEMAP_MAX_URLS', 50000),

        // Sitemap file naming pattern (will append numbers: sitemap-1.xml, sitemap-2.xml)
        'filename_pattern' => env('SITEMAP_FILENAME_PATTERN', 'sitemap-%d.xml'),

        // Index filename
        'index_filename' => env('SITEMAP_INDEX_FILENAME', 'sitemap.xml'),
    ],

];
