<?php

use Daikazu\Sitemap\Controllers\SitemapController;
use Daikazu\Sitemap\Services\SitemapService;
use Daikazu\Sitemap\SitemapServiceProvider;

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
