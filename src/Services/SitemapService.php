<?php

namespace Daikazu\Sitemap\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class SitemapService
{
    /**
     * The cache key for tracking sitemap generation cooldown.
     */
    private const string CACHE_KEY = 'sitemap_generated';

    /**
     * The cache key for storing the sitemap content.
     */
    private const string SITEMAP_CONTENT_KEY = 'sitemap_content';

    /**
     * The path to the sitemap file.
     */
    private readonly string $sitemapPath;

    public function __construct()
    {
        $this->sitemapPath = public_path('sitemap.xml');
    }

    /**
     * Generate a new sitemap if not in local environment and cooldown period has expired.
     *
     * @return bool Whether the sitemap was generated
     */
    public function generateIfDue(): bool
    {
        if ($this->shouldSkipGeneration()) {
            return false;
        }

        $this->generateSitemap();
        $this->startCooldownPeriod();

        return true;
    }

    /**
     * Force regenerate the sitemap regardless of cooldown period.
     *
     * @return bool Whether the sitemap was generated
     */
    public function forceRegenerate(): bool
    {
        $this->generateSitemap();
        $this->startCooldownPeriod();

        return true;
    }

    /**
     * Get the sitemap content from cache or generate it if not available.
     *
     * @return string The sitemap XML content
     */
    public function getSitemapContent(): string
    {
        // Check if sitemap content is in cache
        if (Cache::has(self::SITEMAP_CONTENT_KEY)) {
            return Cache::get(self::SITEMAP_CONTENT_KEY);
        }

        // If not in cache, generate it
        $this->generateSitemap();

        // Return the content from the file
        return File::get($this->sitemapPath);
    }

    /**
     * Get the cooldown period in hours.
     */
    private function getCooldownHours(): int
    {
        return Config::get('sitemap.cooldown_hours', 24);
    }

    /**
     * Determine if sitemap generation should be skipped.
     */
    private function shouldSkipGeneration(): bool
    {
        if (app()->environment('local')) {
            return true;
        }
        return Cache::has(self::CACHE_KEY) && File::exists($this->sitemapPath);
    }

    /**
     * Generate the sitemap using the Artisan command.
     */
    private function generateSitemap(): void
    {
        Artisan::call('app:generate-sitemap');

        // Store the sitemap content in cache
        if (File::exists($this->sitemapPath)) {
            $content = File::get($this->sitemapPath);
            Cache::put(self::SITEMAP_CONTENT_KEY, $content, now()->addHours($this->getCooldownHours()));
        }
    }

    /**
     * Start the cooldown period after successful generation.
     */
    private function startCooldownPeriod(): void
    {
        Cache::put(self::CACHE_KEY, true, now()->addHours($this->getCooldownHours()));
    }
}
