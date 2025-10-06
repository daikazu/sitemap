<?php

namespace Daikazu\Sitemap;

use Daikazu\Sitemap\Commands\GenerateModelSitemapCommand;
use Daikazu\Sitemap\Commands\RegenerateSitemapCommand;
use Daikazu\Sitemap\Commands\SitemapCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SitemapServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('sitemap')
            ->hasConfigFile()
            ->hasViews()
            ->hasRoutes(['web', 'console'])
            ->hasCommands([
                SitemapCommand::class,
                RegenerateSitemapCommand::class,
                GenerateModelSitemapCommand::class,
            ]);
    }
}
