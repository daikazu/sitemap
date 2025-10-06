<?php

use Daikazu\Sitemap\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/**
 * Sitemap route that serves the sitemap from storage.
 */
Route::get('sitemap.xml', [SitemapController::class, 'show']);
