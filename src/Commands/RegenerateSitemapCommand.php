<?php

namespace Daikazu\Sitemap\Commands;

use Daikazu\Sitemap\Services\SitemapService;
use Illuminate\Console\Command;

class RegenerateSitemapCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:regenerate-sitemap';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force regenerate the sitemap and store it in cache';

    /**
     * Execute the console command.
     */
    public function handle(SitemapService $sitemapService): int
    {
        $this->info('Force regenerating sitemap...');

        $result = $sitemapService->forceRegenerate();

        if ($result) {
            $this->info('Sitemap regenerated successfully and stored in cache.');
            return 0;
        }

        $this->error('Failed to regenerate sitemap.');
        return 1;
    }
}
