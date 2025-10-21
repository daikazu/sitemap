<?php

namespace Daikazu\Sitemap\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapIndexGenerator
{
    protected array $urls = [];

    protected int $currentSitemapIndex = 1;

    protected array $sitemapFiles = [];

    /**
     * Check if sitemap index is enabled.
     */
    public function isEnabled(): bool
    {
        return Config::get('sitemap.index.enabled', false);
    }

    /**
     * Get the maximum URLs per sitemap.
     */
    public function getMaxUrlsPerSitemap(): int
    {
        return Config::get('sitemap.index.max_urls_per_sitemap', 50000);
    }

    /**
     * Get the sitemap filename pattern.
     */
    public function getFilenamePattern(): string
    {
        return Config::get('sitemap.index.filename_pattern', 'sitemap-%d.xml');
    }

    /**
     * Get the index filename.
     */
    public function getIndexFilename(): string
    {
        return Config::get('sitemap.index.index_filename', 'sitemap.xml');
    }

    /**
     * Add a URL to be processed.
     */
    public function addUrl(Url $url): void
    {
        $this->urls[] = $url;
    }

    /**
     * Generate sitemap files and index.
     */
    public function generate(): array
    {
        if (! $this->isEnabled()) {
            return $this->generateSingleSitemap();
        }

        return $this->generateSitemapIndex();
    }

    /**
     * Generate a single sitemap (when index is disabled).
     */
    protected function generateSingleSitemap(): array
    {
        $sitemap = Sitemap::create();

        foreach ($this->urls as $url) {
            $sitemap->add($url);
        }

        $disk = Config::get('sitemap.storage.disk', 'public');
        $path = Config::get('sitemap.storage.path', 'sitemaps');
        $filename = Config::get('sitemap.storage.filename', 'sitemap.xml');

        // Build XML manually
        $xml = $this->buildSitemapXml($this->urls);

        $fullPath = $path . '/' . $filename;
        Storage::disk($disk)->put($fullPath, $xml);

        return [$fullPath];
    }

    /**
     * Generate multiple sitemaps and an index.
     */
    protected function generateSitemapIndex(): array
    {
        $maxUrls = $this->getMaxUrlsPerSitemap();
        $chunks = array_chunk($this->urls, $maxUrls);

        $disk = Config::get('sitemap.storage.disk', 'public');
        $path = Config::get('sitemap.storage.path', 'sitemaps');
        $sitemapUrls = [];

        foreach ($chunks as $index => $urlChunk) {
            $sitemapNumber = $index + 1;
            $filename = sprintf($this->getFilenamePattern(), $sitemapNumber);
            $fullPath = $path . '/' . $filename;

            // Build XML manually
            $xml = $this->buildSitemapXml($urlChunk);
            Storage::disk($disk)->put($fullPath, $xml);

            $this->sitemapFiles[] = $fullPath;

            // Track sitemap URL for index
            $publicUrl = $this->getPublicUrl($filename);
            $sitemapUrls[] = $publicUrl;
        }

        // Build sitemap index XML manually
        $indexXml = $this->buildSitemapIndexXml($sitemapUrls);
        $indexFilename = $this->getIndexFilename();
        $indexFullPath = $path . '/' . $indexFilename;
        Storage::disk($disk)->put($indexFullPath, $indexXml);

        $this->sitemapFiles[] = $indexFullPath;

        return $this->sitemapFiles;
    }

    /**
     * Build sitemap XML manually.
     */
    protected function buildSitemapXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        foreach ($urls as $url) {
            $xml .= '  <url>' . PHP_EOL;
            $xml .= '    <loc>' . htmlspecialchars($url->url) . '</loc>' . PHP_EOL;

            if (isset($url->lastModificationDate)) {
                $xml .= '    <lastmod>' . $url->lastModificationDate->format('Y-m-d\TH:i:sP') . '</lastmod>' . PHP_EOL;
            }

            if (isset($url->changeFrequency)) {
                $xml .= '    <changefreq>' . $url->changeFrequency . '</changefreq>' . PHP_EOL;
            }

            if (isset($url->priority)) {
                $xml .= '    <priority>' . number_format($url->priority, 1) . '</priority>' . PHP_EOL;
            }

            $xml .= '  </url>' . PHP_EOL;
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Build sitemap index XML manually.
     */
    protected function buildSitemapIndexXml(array $sitemapUrls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        foreach ($sitemapUrls as $sitemapUrl) {
            $xml .= '  <sitemap>' . PHP_EOL;
            $xml .= '    <loc>' . htmlspecialchars($sitemapUrl) . '</loc>' . PHP_EOL;
            $xml .= '    <lastmod>' . date('Y-m-d\TH:i:sP') . '</lastmod>' . PHP_EOL;
            $xml .= '  </sitemap>' . PHP_EOL;
        }

        $xml .= '</sitemapindex>';

        return $xml;
    }

    /**
     * Get the public URL for a sitemap file.
     */
    protected function getPublicUrl(string $filename): string
    {
        // Use the route-based URL for consistency
        return Config::get('app.url') . '/sitemaps/' . $filename;
    }

    /**
     * Get all generated sitemap files.
     */
    public function getSitemapFiles(): array
    {
        return $this->sitemapFiles;
    }

    /**
     * Clear URLs and reset state.
     */
    public function reset(): void
    {
        $this->urls = [];
        $this->currentSitemapIndex = 1;
        $this->sitemapFiles = [];
    }
}
