<?php

namespace Daikazu\Sitemap\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

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
     * Get the storage disk for sitemaps.
     */
    private function getDisk(): string
    {
        return Config::get('sitemap.storage.disk', 'public');
    }

    /**
     * Get the storage path for sitemaps.
     */
    private function getStoragePath(): string
    {
        return Config::get('sitemap.storage.path', 'sitemaps');
    }

    /**
     * Get the sitemap filename.
     */
    private function getFilename(): string
    {
        // If index is enabled, use index filename, otherwise use regular filename
        if (Config::get('sitemap.index.enabled', false)) {
            return Config::get('sitemap.index.index_filename', 'sitemap.xml');
        }

        return Config::get('sitemap.storage.filename', 'sitemap.xml');
    }

    /**
     * Get the full path to the sitemap file within the storage disk.
     */
    private function getFullPath(): string
    {
        return $this->getStoragePath() . '/' . $this->getFilename();
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

        // If not in cache, check if file exists in storage
        $disk = Storage::disk($this->getDisk());
        if ($disk->exists($this->getFullPath())) {
            $content = $disk->get($this->getFullPath());
            // Cache it for next time
            Cache::put(self::SITEMAP_CONTENT_KEY, $content, now()->addHours($this->getCooldownHours()));
            return $content;
        }

        // If not in cache or storage, generate it
        $this->generateSitemap();

        // Return the content from the file
        return $disk->get($this->getFullPath());
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
        return Cache::has(self::CACHE_KEY) && Storage::disk($this->getDisk())->exists($this->getFullPath());
    }

    /**
     * Generate the sitemap using the Artisan command.
     */
    private function generateSitemap(): void
    {
        Artisan::call('app:generate-sitemap');

        // Store the sitemap content in cache
        $disk = Storage::disk($this->getDisk());
        if ($disk->exists($this->getFullPath())) {
            $content = $disk->get($this->getFullPath());
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
