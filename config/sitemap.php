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
        '.jpg', '.jpeg', '.png', '.gif', '.webp', '.css', '.js', '.pdf', '.zip',
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

    /*
    |--------------------------------------------------------------------------
    | Model-Based Sitemap Generation
    |--------------------------------------------------------------------------
    |
    | Configure Eloquent models to be included in the sitemap. This is more
    | efficient than crawling and can include dynamic/authenticated content.
    | Works in hybrid mode with crawling or standalone.
    |
    */

    'models' => [
        // Example configuration:
        // \App\Models\Post::class => [
        //     'enabled' => true,
        //     'url' => fn($post) => route('posts.show', $post->slug),
        //     'lastmod' => 'updated_at', // column name or closure: fn($post) => $post->updated_at
        //     'changefreq' => 'weekly', // always, hourly, daily, weekly, monthly, yearly, never
        //     'priority' => 0.8, // 0.0 to 1.0 or closure: fn($post) => $post->is_featured ? 0.9 : 0.7
        //     'query' => fn($query) => $query->where('published', true)->orderBy('updated_at', 'desc'),
        //     'chunk_size' => 1000, // Optional: Process models in chunks (default: 1000)
        // ],
    ],

    // Generate mode: 'crawl', 'models', or 'hybrid'
    // - 'crawl': Only crawl the website (default behavior)
    // - 'models': Only generate from configured models
    // - 'hybrid': Combine both crawled URLs and model-based URLs
    'generate_mode' => env('SITEMAP_GENERATE_MODE', 'crawl'),

];
