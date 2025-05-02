<?php

use Daikazu\Sitemap\Services\SitemapService;
use Illuminate\Support\Facades\Route;

/**
 * Sitemap route that serves the sitemap from cache.
 */
Route::get('sitemap.xml', fn (SitemapService $sitemapService) => response($sitemapService->getSitemapContent())
    ->header('Content-Type', 'application/xml'));
