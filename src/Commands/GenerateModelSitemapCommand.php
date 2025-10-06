<?php

namespace Daikazu\Sitemap\Commands;

use Daikazu\Sitemap\Services\ModelSitemapGenerator;
use Daikazu\Sitemap\Services\SitemapIndexGenerator;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\Sitemap\Sitemap;

class GenerateModelSitemapCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-model-sitemap
                            {--output=sitemap.xml : Sitemap filename}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a sitemap from configured Eloquent models';

    /**
     * Execute the console command.
     */
    public function handle(ModelSitemapGenerator $modelGenerator, SitemapIndexGenerator $indexGenerator): int
    {
        $this->info('Starting model-based sitemap generation...');

        try {
            // Generate URLs from models
            $modelUrls = $modelGenerator->generate();

            if (empty($modelUrls)) {
                $this->warn('No URLs generated from models. Please check your configuration.');

                return 1;
            }

            $this->info('Generated ' . count($modelUrls) . ' URLs from models');

            // Create sitemap
            $sitemap = Sitemap::create();

            foreach ($modelUrls as $url) {
                $sitemap->add($url);
            }

            // Get storage configuration
            $disk = config('sitemap.storage.disk', 'public');
            $path = config('sitemap.storage.path', 'sitemaps');

            // Create the storage directory if it doesn't exist
            $storagePath = storage_path('app/' . $disk . '/' . $path);
            if (! file_exists($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            // Check if sitemap index is enabled
            $useIndex = $indexGenerator->isEnabled();

            if ($useIndex) {
                $this->info('Generating sitemap index...');

                // Add all URLs to the index generator
                foreach ($sitemap->getTags() as $tag) {
                    $indexGenerator->addUrl($tag);
                }

                // Generate sitemaps and index
                $files = $indexGenerator->generate();

                $this->info('Sitemap generation complete!');
                $this->info('Total URLs from models: ' . count($modelUrls));
                $this->info('Generated files:');
                foreach ($files as $file) {
                    $this->info("  - {$file}");
                }
            } else {
                $filename = $this->option('output');

                // Save the sitemap to a temporary location first
                $tempPath = sys_get_temp_dir() . '/' . $filename;
                $sitemap->writeToFile($tempPath);

                // Move to storage using Laravel's Storage facade
                $fullPath = $path . '/' . $filename;
                Storage::disk($disk)->put($fullPath, file_get_contents($tempPath));

                // Clean up temp file
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }

                $this->info('Sitemap generation complete!');
                $this->info('Total URLs from models: ' . count($modelUrls));
                $this->info("Sitemap saved to: {$disk}/{$fullPath}");
            }

            return 0;

        } catch (Exception $e) {
            $this->error('Failed to generate sitemap: ' . $e->getMessage());
            $this->error($e->getTraceAsString());

            return 1;
        }
    }
}
