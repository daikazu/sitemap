<?php

use Daikazu\Sitemap\Controllers\SitemapController;
use Daikazu\Sitemap\Services\SitemapService;
use Daikazu\Sitemap\SitemapServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

test('sitemap service provider is registered', function (): void {
    $app = app();

    expect($app->getProviders(SitemapServiceProvider::class))->toHaveCount(1);
});

test('sitemap controller can be instantiated', function (): void {
    $service = app(SitemapService::class);
    $controller = new SitemapController($service);

    expect($controller)->toBeInstanceOf(SitemapController::class);
});

test('sitemap storage configuration has defaults', function (): void {
    expect(config('sitemap.storage.disk'))->toBe('public');
    expect(config('sitemap.storage.path'))->toBe('sitemaps');
    expect(config('sitemap.storage.filename'))->toBe('sitemap.xml');
});

describe('SitemapService', function () {
    beforeEach(function () {
        Cache::flush();
        Storage::fake('public');
    });

    test('generateIfDue returns false when cooldown is active', function (): void {
        Cache::put('sitemap_generated', true, now()->addHours(24));
        Storage::disk('public')->put('sitemaps/sitemap.xml', '<xml>test</xml>');

        $service = app(SitemapService::class);
        $result = $service->generateIfDue();

        expect($result)->toBeFalse();
    });

    test('getSitemapContent returns cached content when available', function (): void {
        $cachedContent = '<?xml version="1.0"?><urlset></urlset>';
        Cache::put('sitemap_content', $cachedContent, now()->addHours(24));

        $service = app(SitemapService::class);
        $content = $service->getSitemapContent();

        expect($content)->toBe($cachedContent);
    });

    test('getSitemapContent retrieves from storage and caches it when not in cache', function (): void {
        $storageContent = '<?xml version="1.0"?><urlset><url><loc>https://example.com</loc></url></urlset>';
        Storage::disk('public')->put('sitemaps/sitemap.xml', $storageContent);

        $service = app(SitemapService::class);
        $content = $service->getSitemapContent();

        expect($content)
            ->toBe($storageContent)
            ->and(Cache::has('sitemap_content'))->toBeTrue()
            ->and(Cache::get('sitemap_content'))->toBe($storageContent);
    });

    test('custom storage configuration is respected', function (): void {
        Config::set('sitemap.storage.disk', 'local');
        Config::set('sitemap.storage.path', 'custom-path');
        Config::set('sitemap.storage.filename', 'custom-sitemap.xml');

        Storage::fake('local');
        Storage::disk('local')->put('custom-path/custom-sitemap.xml', '<xml>custom</xml>');

        $service = app(SitemapService::class);
        $content = $service->getSitemapContent();

        expect($content)->toBe('<xml>custom</xml>');
    });
});
