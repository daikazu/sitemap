<?php

namespace Daikazu\Sitemap\Controllers;

use Daikazu\Sitemap\Services\SitemapService;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class SitemapController extends Controller
{
    public function __construct(
        protected SitemapService $sitemapService
    ) {}

    /**
     * Serve the sitemap XML file.
     */
    public function show(): Response
    {
        $content = $this->sitemapService->getSitemapContent();

        return response($content)
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
