<?php

use Daikazu\Sitemap\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/**
 * Sitemap routes that serve the sitemap(s) from storage.
 */
Route::get('sitemap.xml', [SitemapController::class, 'show'])->name('sitemap.index');

// Route for individual sitemaps when using sitemap index
Route::get('sitemaps/{filename}', [SitemapController::class, 'show'])->name('sitemap.show')
    ->where('filename', '.*\.xml');
