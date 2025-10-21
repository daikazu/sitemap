<?php

namespace Daikazu\Sitemap\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ClearSitemapCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-sitemap';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all generated sitemaps and reset cache';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Clearing sitemap files and cache...');

        // Get storage configuration
        $disk = config('sitemap.storage.disk', 'public');
        $path = config('sitemap.storage.path', 'sitemaps');
        $storage = Storage::disk($disk);

        $filesDeleted = 0;

        // Check if index mode is enabled
        if (config('sitemap.index.enabled', false)) {
            // Delete all sitemap files in the directory
            $this->info('Index mode enabled - clearing all sitemap files...');

            if ($storage->exists($path)) {
                $files = $storage->files($path);
                foreach ($files as $file) {
                    if (str_ends_with($file, '.xml')) {
                        $storage->delete($file);
                        $this->line("Deleted: {$file}");
                        $filesDeleted++;
                    }
                }
            }
        } else {
            // Delete single sitemap file
            $filename = config('sitemap.storage.filename', 'sitemap.xml');
            $fullPath = $path . '/' . $filename;

            if ($storage->exists($fullPath)) {
                $storage->delete($fullPath);
                $this->line("Deleted: {$fullPath}");
                $filesDeleted++;
            }
        }

        // Clear cache entries
        Cache::forget('sitemap_generated');
        Cache::forget('sitemap_content');
        $this->info('Cache cleared');

        if ($filesDeleted > 0) {
            $this->info("Successfully deleted {$filesDeleted} sitemap file(s)");
        } else {
            $this->warn('No sitemap files found to delete');
        }

        $this->info('Sitemap cleared successfully!');

        return 0;
    }
}
