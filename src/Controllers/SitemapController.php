<?php

namespace Daikazu\Sitemap\Controllers;

use Daikazu\Sitemap\Services\SitemapService;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class SitemapController extends Controller
{
    public function __construct(
        protected SitemapService $sitemapService
    ) {}

    /**
     * Serve the sitemap XML file (index or single sitemap).
     */
    public function show(?string $filename = null): Response
    {
        // If filename is provided, serve individual sitemap from index
        if ($filename) {
            return $this->showIndividualSitemap($filename);
        }

        // Serve main sitemap (or index)
        $content = $this->sitemapService->getSitemapContent();

        return response($content)
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Serve an individual sitemap from the index.
     */
    protected function showIndividualSitemap(string $filename): Response
    {
        // Sanitize filename to prevent directory traversal
        $filename = basename($filename);

        $disk = Config::get('sitemap.storage.disk', 'public');
        $path = Config::get('sitemap.storage.path', 'sitemaps');
        $fullPath = $path . '/' . $filename;

        if (! Storage::disk($disk)->exists($fullPath)) {
            abort(404, 'Sitemap not found');
        }

        $content = Storage::disk($disk)->get($fullPath);

        return response($content)
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
