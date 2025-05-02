<?php

use Daikazu\Sitemap\SitemapServiceProvider;

test('sitemap service provider is registered', function (): void {
    $app = app();

    expect($app->getProviders(SitemapServiceProvider::class))->toHaveCount(1);
});
