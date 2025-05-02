<?php

use Daikazu\Sitemap\Services\SitemapService;
use Illuminate\Support\Facades\Config;

/**
 * Schedule sitemap generation with cooldown protection.
 * Checks a configured interval if the cooldown period has expired and generates a new sitemap.
 */
if (Config::get('sitemap.schedule.enabled', true)) {
    Schedule::call(function (): void {
        app(SitemapService::class)->generateIfDue();
    })->everyMinute();

    /**
     * Force regenerate the sitemap daily at configured time.
     * This ensures the sitemap is always up to date, even if the instance goes to sleep.
     */
    Schedule::command('app:regenerate-sitemap')
        ->dailyAt(Config::get('sitemap.schedule.daily_time', '00:00'));
}
