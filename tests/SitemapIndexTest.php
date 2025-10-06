<?php

use Daikazu\Sitemap\Services\SitemapIndexGenerator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Spatie\Sitemap\Tags\Url;

describe('SitemapIndexGenerator', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    test('isEnabled returns false when index is disabled', function (): void {
        Config::set('sitemap.index.enabled', false);

        $generator = app(SitemapIndexGenerator::class);

        expect($generator->isEnabled())->toBeFalse();
    });

    test('isEnabled returns true when index is enabled', function (): void {
        Config::set('sitemap.index.enabled', true);

        $generator = app(SitemapIndexGenerator::class);

        expect($generator->isEnabled())->toBeTrue();
    });

    test('getMaxUrlsPerSitemap returns configured value', function (): void {
        Config::set('sitemap.index.max_urls_per_sitemap', 1000);

        $generator = app(SitemapIndexGenerator::class);

        expect($generator->getMaxUrlsPerSitemap())->toBe(1000);
    });

    test('generates single sitemap when index is disabled', function (): void {
        Config::set('sitemap.index.enabled', false);
        Config::set('sitemap.storage.disk', 'public');
        Config::set('sitemap.storage.path', 'sitemaps');
        Config::set('sitemap.storage.filename', 'sitemap.xml');

        $generator = app(SitemapIndexGenerator::class);

        // Add some URLs
        $generator->addUrl(Url::create('https://example.com'));
        $generator->addUrl(Url::create('https://example.com/about'));

        $files = $generator->generate();

        expect($files)->toHaveCount(1);
        expect($files[0])->toBe('sitemaps/sitemap.xml');
        expect(Storage::disk('public')->exists('sitemaps/sitemap.xml'))->toBeTrue();
    });

    test('generates multiple sitemaps and index when enabled', function (): void {
        Config::set('sitemap.index.enabled', true);
        Config::set('sitemap.index.max_urls_per_sitemap', 2);
        Config::set('sitemap.index.filename_pattern', 'sitemap-%d.xml');
        Config::set('sitemap.index.index_filename', 'sitemap.xml');
        Config::set('sitemap.storage.disk', 'public');
        Config::set('sitemap.storage.path', 'sitemaps');
        Config::set('app.url', 'https://example.com');

        $generator = app(SitemapIndexGenerator::class);

        // Add 5 URLs (should create 3 sitemaps: 2+2+1)
        $generator->addUrl(Url::create('https://example.com/1'));
        $generator->addUrl(Url::create('https://example.com/2'));
        $generator->addUrl(Url::create('https://example.com/3'));
        $generator->addUrl(Url::create('https://example.com/4'));
        $generator->addUrl(Url::create('https://example.com/5'));

        $files = $generator->generate();

        // Should have 3 sitemap files + 1 index = 4 total
        expect($files)->toHaveCount(4);

        // Check individual sitemaps exist
        expect(Storage::disk('public')->exists('sitemaps/sitemap-1.xml'))->toBeTrue();
        expect(Storage::disk('public')->exists('sitemaps/sitemap-2.xml'))->toBeTrue();
        expect(Storage::disk('public')->exists('sitemaps/sitemap-3.xml'))->toBeTrue();

        // Check index exists
        expect(Storage::disk('public')->exists('sitemaps/sitemap.xml'))->toBeTrue();

        // Verify index contains references to sub-sitemaps
        $indexContent = Storage::disk('public')->get('sitemaps/sitemap.xml');
        expect($indexContent)->toContain('sitemap-1.xml');
        expect($indexContent)->toContain('sitemap-2.xml');
        expect($indexContent)->toContain('sitemap-3.xml');
    });

    test('reset clears all URLs and state', function (): void {
        $generator = app(SitemapIndexGenerator::class);

        $generator->addUrl(Url::create('https://example.com'));
        $generator->addUrl(Url::create('https://example.com/about'));

        $generator->reset();

        // After reset, generating should create empty/minimal sitemap
        $files = $generator->generate();

        expect($files)->toHaveCount(1);
    });

    test('custom filename pattern is respected', function (): void {
        Config::set('sitemap.index.enabled', true);
        Config::set('sitemap.index.max_urls_per_sitemap', 1);
        Config::set('sitemap.index.filename_pattern', 'custom-%d.xml');
        Config::set('sitemap.storage.disk', 'public');
        Config::set('sitemap.storage.path', 'sitemaps');
        Config::set('app.url', 'https://example.com');

        $generator = app(SitemapIndexGenerator::class);

        $generator->addUrl(Url::create('https://example.com/1'));
        $generator->addUrl(Url::create('https://example.com/2'));

        $generator->generate();

        expect(Storage::disk('public')->exists('sitemaps/custom-1.xml'))->toBeTrue();
        expect(Storage::disk('public')->exists('sitemaps/custom-2.xml'))->toBeTrue();
    });
});
