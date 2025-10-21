<?php

namespace Daikazu\Sitemap\Services;

use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Spatie\Sitemap\Tags\Url;

class ModelSitemapGenerator
{
    protected array $urls = [];

    /**
     * Check if model-based generation is enabled.
     */
    public function isEnabled(): bool
    {
        $mode = Config::get('sitemap.generate_mode', 'crawl');

        return in_array($mode, ['models', 'hybrid']);
    }

    /**
     * Get the generation mode.
     */
    public function getMode(): string
    {
        return Config::get('sitemap.generate_mode', 'crawl');
    }

    /**
     * Get all configured models.
     */
    public function getConfiguredModels(): array
    {
        return Config::get('sitemap.models', []);
    }

    /**
     * Generate URLs from all configured models.
     */
    public function generate(): array
    {
        $models = $this->getConfiguredModels();

        foreach ($models as $modelClass => $config) {
            if (! $this->isModelEnabled($config)) {
                continue;
            }

            $this->processModel($modelClass, $config);
        }

        return $this->urls;
    }

    /**
     * Check if a specific model is enabled.
     */
    protected function isModelEnabled(array $config): bool
    {
        return $config['enabled'] ?? true;
    }

    /**
     * Process a single model and generate URLs.
     */
    protected function processModel(string $modelClass, array $config): void
    {
        if (! class_exists($modelClass)) {
            return;
        }

        $query = $modelClass::query();

        // Apply custom query modifications if provided
        if (isset($config['query']) && is_callable($config['query'])) {
            $query = call_user_func($config['query'], $query);
        }

        $chunkSize = $config['chunk_size'] ?? 1000;

        // Process in chunks to avoid memory issues
        $query->chunk($chunkSize, function ($models) use ($config) {
            foreach ($models as $model) {
                $url = $this->generateUrlForModel($model, $config);

                if ($url) {
                    $this->urls[] = $url;
                }
            }
        });
    }

    /**
     * Generate a URL object for a specific model instance.
     */
    protected function generateUrlForModel(Model $model, array $config): ?Url
    {
        // Generate the URL
        if (! isset($config['url']) || ! is_callable($config['url'])) {
            return null;
        }

        $urlString = call_user_func($config['url'], $model);

        if (! $urlString) {
            return null;
        }

        $url = Url::create($urlString);

        // Set last modification date
        $lastmod = $this->getLastModified($model, $config);
        if ($lastmod) {
            $url->setLastModificationDate($lastmod);
        }

        // Set change frequency
        $changefreq = $this->getChangeFrequency($model, $config);
        if ($changefreq) {
            $url->setChangeFrequency($changefreq);
        }

        // Set priority
        $priority = $this->getPriority($model, $config);
        if ($priority !== null) {
            $url->setPriority($priority);
        }

        return $url;
    }

    /**
     * Get the last modified date for a model.
     */
    protected function getLastModified(Model $model, array $config): ?DateTime
    {
        if (! isset($config['lastmod'])) {
            return null;
        }

        $lastmod = $config['lastmod'];

        // If it's a closure, call it
        if (is_callable($lastmod)) {
            $result = call_user_func($lastmod, $model);

            return $result instanceof DateTime ? $result : new DateTime($result);
        }

        // If it's a column name, get the value
        if (is_string($lastmod) && isset($model->{$lastmod})) {
            $value = $model->{$lastmod};

            // Check Carbon first since it extends DateTime
            if ($value instanceof \Illuminate\Support\Carbon) {
                return DateTime::createFromFormat('Y-m-d H:i:s', $value->format('Y-m-d H:i:s'));
            }

            if ($value instanceof DateTime) {
                return $value;
            }

            return new DateTime($value);
        }

        return null;
    }

    /**
     * Get the change frequency for a model.
     */
    protected function getChangeFrequency(Model $model, array $config): ?string
    {
        if (! isset($config['changefreq'])) {
            return null;
        }

        $changefreq = $config['changefreq'];

        // If it's a closure, call it
        if (is_callable($changefreq)) {
            return call_user_func($changefreq, $model);
        }

        // Otherwise, use the value directly
        return $changefreq;
    }

    /**
     * Get the priority for a model.
     */
    protected function getPriority(Model $model, array $config): ?float
    {
        if (! isset($config['priority'])) {
            return null;
        }

        $priority = $config['priority'];

        // If it's a closure, call it
        if (is_callable($priority)) {
            return (float) call_user_func($priority, $model);
        }

        // Otherwise, use the value directly
        return (float) $priority;
    }

    /**
     * Get all generated URLs.
     */
    public function getUrls(): array
    {
        return $this->urls;
    }

    /**
     * Clear all generated URLs.
     */
    public function reset(): void
    {
        $this->urls = [];
    }
}
