<?php

namespace Daikazu\Sitemap\Commands;

use Daikazu\Sitemap\Services\ModelSitemapGenerator;
use Daikazu\Sitemap\Services\SitemapIndexGenerator;
use DateTime;
use Exception;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Spatie\Crawler\Crawler;
use Spatie\Crawler\CrawlObservers\CrawlObserver;
use Spatie\Crawler\CrawlProfiles\CrawlInternalUrls;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-sitemap
                            {baseUrl? : The base URL of your site}
                            {--depth=30 : Maximum crawl depth}
                            {--concurrency=5 : Number of concurrent requests}
                            {--output=sitemap.xml : Sitemap filename}
                            {--exclude= : Comma-separated list of directories to exclude}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a sitemap by crawling the site with directory exclusion support';

    /**
     * Crawled URLs counter
     */
    protected int $crawledCount = 0;

    /**
     * URLs that have been processed (to handle canonicals)
     */
    protected array $processedUrls = [];

    /**
     * Normalized URL mappings
     */
    protected array $urlMappings = [];

    /**
     * Already crawled normalized URLs
     */
    protected array $crawledNormalizedUrls = [];

    /**
     * Special pages with higher priority
     */
    protected array $specialPages = [];

    /**
     * Directories to exclude from crawling
     */
    protected array $excludedDirectories = [];

    /**
     * URLs already added from models (to prevent duplicates in hybrid mode)
     */
    protected array $modelUrls = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Allow unlimited execution time for large sites
        set_time_limit(0);

        // Increase memory limit if needed
        ini_set('memory_limit', '512M');

        // Get the base URL from the command argument or use the APP_URL from .env
        $baseUrl = $this->argument('baseUrl') ?? config('app.url');

        // Get command options
        $depth = (int) $this->option('depth');
        $concurrency = (int) $this->option('concurrency');
        $output = $this->option('output');
        $excludeString = $this->option('exclude');

        // Parse excluded directories
        if (! empty($excludeString)) {
            $this->excludedDirectories = array_map('trim', explode(',', $excludeString));
            $this->info('Excluding directories: ' . implode(', ', $this->excludedDirectories));
        }

        // Validate the base URL
        if (! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $this->error("Invalid base URL: {$baseUrl}");
            return 1;
        }

        // Make sure the URL ends with a slash
        if (! str_ends_with((string) $baseUrl, '/')) {
            $baseUrl .= '/';
        }

        // Define special pages with priority
        $this->specialPages = [
            rtrim((string) $baseUrl, '/')               => 1.0, // Homepage
            rtrim((string) $baseUrl, '/') . '/contact'  => 0.9,
            rtrim((string) $baseUrl, '/') . '/about'    => 0.9,
            rtrim((string) $baseUrl, '/') . '/about-us' => 0.9,
            rtrim((string) $baseUrl, '/') . '/products' => 0.9,
            rtrim((string) $baseUrl, '/') . '/services' => 0.9,
        ];

        $this->info('Starting sitemap generation...');
        $this->info("Base URL: {$baseUrl}");
        $this->info("Max Depth: {$depth}");

        $this->info('Starting crawler...');
        $this->output->progressStart();

        try {
            // Check generation mode
            $generateMode = config('sitemap.generate_mode', 'crawl');
            $modelGenerator = app(ModelSitemapGenerator::class);

            // Check if sitemap index is enabled
            $indexGenerator = app(SitemapIndexGenerator::class);
            $useIndex = $indexGenerator->isEnabled();

            // Create sitemap (will be used to collect URLs)
            $sitemap = Sitemap::create();

            // Generate URLs from models if enabled (models or hybrid mode)
            if (in_array($generateMode, ['models', 'hybrid'])) {
                $this->info('Generating URLs from models...');
                $modelUrls = $modelGenerator->generate();
                $this->info('Generated ' . count($modelUrls) . ' URLs from models');

                foreach ($modelUrls as $url) {
                    $sitemap->add($url);
                    // Track model URLs to prevent duplicates during crawling
                    $this->modelUrls[] = $this->normalizeUrl($url->url);
                }
            }

            // Skip crawling if mode is 'models' only
            if ($generateMode === 'models') {
                $this->info('Skipping crawl (models-only mode)');
                $this->info('Processing ' . count($modelUrls) . ' model-based URLs...');

                // Skip to the saving part
                goto save_sitemap;
            }

            // Create the crawler
            $crawler = Crawler::create([
                'verify'          => false,
                'cookies'         => true,
                'allow_redirects' => true,
                'headers'         => [
                    'User-Agent' => 'Laravel Sitemap Generator/1.0',
                ],
            ])
                ->setCrawlObserver(new class($this, $sitemap, $baseUrl) extends CrawlObserver
                {
                    public function __construct(protected $command, protected $sitemap, protected $baseUrl) {}

                    public function willCrawl(UriInterface $url, ?string $linkText): void
                    {
                        // Nothing special to do before crawling
                    }

                    public function crawled(UriInterface $url, ResponseInterface $response, ?UriInterface $foundOnUrl = null, ?string $linkText = null): void
                    {
                        // Skip non-200 responses
                        if ($response->getStatusCode() !== 200) {
                            return;
                        }

                        $urlString = (string) $url;

                        // Check if this URL was already added from models (hybrid mode deduplication)
                        if ($this->command->isModelUrl($urlString)) {
                            return;
                        }

                        $this->command->incrementCrawledCount();
                        $this->command->progressAdvance();

                        // Check for canonical URL
                        $body = (string) $response->getBody();
                        $canonicalUrl = $this->extractCanonicalUrl($body);

                        if ($canonicalUrl && $canonicalUrl !== $urlString) {
                            // If canonical points elsewhere and we've seen it, skip this URL
                            if (in_array($canonicalUrl, $this->command->getProcessedUrls())) {
                                return;
                            }

                            // Use the canonical URL instead
                            $urlString = $canonicalUrl;
                        }

                        // Skip paginated URLs in the sitemap
                        $urlComponents = parse_url($urlString);
                        if (isset($urlComponents['query'])) {
                            parse_str($urlComponents['query'], $queryParams);
                            if (isset($queryParams['page']) && is_numeric($queryParams['page']) && (int) $queryParams['page'] > 1) {
                                // We'll still crawl this page to find links, but won't add it to the sitemap
                                return;
                            }
                        }

                        // Create the URL object
                        $sitemapUrl = Url::create($urlString);

                        // Check if it's a special page with higher priority
                        $specialPagePriority = $this->command->getSpecialPagePriority($urlString);
                        if ($specialPagePriority !== null) {
                            $sitemapUrl->setPriority($specialPagePriority)
                                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY);
                        } else {
                            // Default priority for regular pages
                            $sitemapUrl->setPriority(0.7)
                                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY);
                        }

                        // Set last modification date
                        $lastModified = $this->getLastModifiedDate($response);
                        if ($lastModified !== null) {
                            $sitemapUrl->setLastModificationDate($lastModified);
                        }

                        // Add URL to sitemap
                        $this->sitemap->add($sitemapUrl);

                        // Remember we processed this URL
                        $this->command->addToProcessedUrls($urlString);
                    }

                    public function crawlFailed(UriInterface $url, RequestException $requestException, ?UriInterface $foundOnUrl = null, ?string $linkText = null): void
                    {
                        // Skip failed URLs
                    }

                    private function extractCanonicalUrl(string $html): ?string
                    {
                        preg_match('/<link[^>]*rel=["\']canonical["\'][^>]*href=["\']([^"\']+)["\']/i', $html, $matches);

                        if (! empty($matches[1])) {
                            return $matches[1];
                        }

                        return null;
                    }

                    private function getLastModifiedDate(ResponseInterface $response): ?DateTime
                    {
                        // Try to get last modified from headers
                        $lastModified = $response->getHeader('Last-Modified');
                        if (! empty($lastModified)) {
                            try {
                                return new DateTime($lastModified[0]);
                            } catch (Exception) {
                                // If date parsing fails, continue
                            }
                        }

                        // Default to current time
                        return new DateTime;
                    }
                })
                ->setCrawlProfile(new class($baseUrl, $this->excludedDirectories) extends CrawlInternalUrls
                {
                    protected $crawledNormalizedUrls = [];

                    public function __construct(string $baseUrl, protected array $excludedDirectories)
                    {
                        parent::__construct($baseUrl);
                    }

                    public function shouldCrawl(UriInterface $url): bool
                    {
                        $urlString = (string) $url;

                        // Normalize URL by removing tracking parameters
                        $normalizedUrl = $this->normalizeUrl($urlString);

                        // If the normalized URL is different, only crawl one version
                        if ($normalizedUrl !== $urlString) {
                            // Only crawl one version of the URL
                            if (in_array($normalizedUrl, $this->crawledNormalizedUrls)) {
                                return false;
                            }
                            $this->crawledNormalizedUrls[] = $normalizedUrl;
                        }

                        // Check if URL is in an excluded directory
                        $urlPath = parse_url($urlString, PHP_URL_PATH) ?? '';
                        foreach ($this->excludedDirectories as $excludedDir) {
                            $excludedDir = trim((string) $excludedDir, '/');
                            if (! empty($excludedDir) && str_starts_with(trim($urlPath, '/'), $excludedDir)) {
                                return false;
                            }
                        }

                        // Skip common non-content URLs
                        $skipPatterns = config('sitemap.skip_patterns') ?? [];

                        foreach ($skipPatterns as $pattern) {
                            if (str_contains((string) $url, (string) $pattern)) {
                                return false;
                            }
                        }

                        // Skip URLs with certain query parameters, but allow pagination
                        $urlComponents = parse_url((string) $url);
                        if (isset($urlComponents['query'])) {
                            $skipQueryParams = ['sort', 'order', 'view', 'filter'];
                            parse_str($urlComponents['query'], $queryParams);

                            foreach ($skipQueryParams as $param) {
                                if (array_key_exists($param, $queryParams)) {
                                    return false;
                                }
                            }

                            // Allow pagination parameters with configurable limit
                            if (isset($queryParams['page']) && is_numeric($queryParams['page'])) {
                                $pageNum = (int) $queryParams['page'];
                                $maxDepth = config('sitemap.max_pagination_depth', 100);

                                // Only enforce limit if max_pagination_depth is set and > 0
                                if ($maxDepth && $pageNum > $maxDepth) {
                                    return false;
                                }
                            }
                        }

                        return parent::shouldCrawl($url);
                    }

                    protected function normalizeUrl(string $url): string
                    {
                        $parsed = parse_url($url);
                        if (isset($parsed['query'])) {
                            parse_str($parsed['query'], $params);

                            // Remove tracking parameters
                            $paramsToRemove = [
                                'utm_source', 'utm_medium', 'utm_campaign',
                                'utm_term', 'utm_content', 'gclid', 'fbclid',
                                'sessionid', 'PHPSESSID', '_ga', 'ref',
                            ];

                            foreach ($paramsToRemove as $param) {
                                if (isset($params[$param])) {
                                    unset($params[$param]);
                                }
                            }

                            // Rebuild query with remaining parameters (sorted alphabetically)
                            ksort($params);
                            $cleanQuery = http_build_query($params);

                            // Rebuild URL
                            $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
                            $host = $parsed['host'] ?? '';
                            $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
                            $path = $parsed['path'] ?? '';
                            $query = empty($cleanQuery) ? '' : '?' . $cleanQuery;
                            $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

                            return $scheme . $host . $port . $path . $query . $fragment;
                        }

                        return $url;
                    }
                })
                ->ignoreRobots()
                ->setMaximumDepth($depth)
                ->setConcurrency($concurrency)
                ->acceptNofollowLinks();

            // Start the crawl
            $crawler->startCrawling($baseUrl);

            $this->output->progressFinish();

            save_sitemap:

            // Get storage configuration
            $disk = config('sitemap.storage.disk', 'public');
            $path = config('sitemap.storage.path', 'sitemaps');

            // Create the storage directory if it doesn't exist
            $storagePath = storage_path('app/' . $disk . '/' . $path);
            if (! file_exists($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            // Generate sitemap(s) based on configuration
            if ($useIndex) {
                $this->info("\nGenerating sitemap index...");

                // Add all URLs from sitemap to the index generator
                foreach ($sitemap->getTags() as $tag) {
                    $indexGenerator->addUrl($tag);
                }

                // Generate sitemaps and index
                $files = $indexGenerator->generate();

                $this->info('Sitemap generation complete!');

                if ($generateMode === 'models') {
                    $this->info('Total URLs from models: ' . count($modelUrls));
                } elseif ($generateMode === 'hybrid') {
                    $this->info("Total URLs: {$this->crawledCount} (crawled) + " . count($modelUrls) . ' (models) = ' . ($this->crawledCount + count($modelUrls)));
                } else {
                    $this->info("Total URLs crawled: {$this->crawledCount}");
                }

                $this->info('Generated files:');
                foreach ($files as $file) {
                    $this->info("  - {$file}");
                }
            } else {
                $filename = config('sitemap.storage.filename', 'sitemap.xml');

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

                $this->info("\nSitemap generation complete!");

                if ($generateMode === 'models') {
                    $this->info('Total URLs from models: ' . count($modelUrls));
                } elseif ($generateMode === 'hybrid') {
                    $totalUrls = $this->crawledCount + (isset($modelUrls) ? count($modelUrls) : 0);
                    $this->info("Total URLs: {$this->crawledCount} (crawled) + " . count($modelUrls) . " (models) = {$totalUrls}");
                } else {
                    $this->info("Total URLs crawled: {$this->crawledCount}");
                }

                $this->info("Sitemap saved to: {$disk}/{$fullPath}");
            }

            return 0;

        } catch (Exception $e) {
            $this->output->progressFinish();
            $this->error("\nFailed to generate sitemap: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Increment crawled count
     */
    public function incrementCrawledCount(): void
    {
        $this->crawledCount++;
    }

    /**
     * Progress bar update
     */
    public function progressAdvance(): void
    {
        $this->output->progressAdvance();
    }

    /**
     * Add a URL to processed list
     */
    public function addToProcessedUrls(string $url): void
    {
        $this->processedUrls[] = $url;
    }

    /**
     * Get processed URLs
     */
    public function getProcessedUrls(): array
    {
        return $this->processedUrls;
    }

    /**
     * Get priority for special pages
     */
    public function getSpecialPagePriority(string $url): ?float
    {
        $urlNormalized = rtrim($url, '/');

        return $this->specialPages[$urlNormalized] ?? null;
    }

    /**
     * Check if URL was already added from models
     */
    public function isModelUrl(string $url): bool
    {
        $normalized = $this->normalizeUrl($url);

        return in_array($normalized, $this->modelUrls);
    }

    /**
     * Normalize URL for comparison (remove trailing slashes, etc.)
     */
    protected function normalizeUrl(string $url): string
    {
        // Parse URL
        $parsed = parse_url($url);

        // Remove tracking parameters
        $paramsToRemove = [
            'utm_source', 'utm_medium', 'utm_campaign',
            'utm_term', 'utm_content', 'gclid', 'fbclid',
            'sessionid', 'PHPSESSID', '_ga', 'ref',
        ];

        $params = [];
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $params);

            foreach ($paramsToRemove as $param) {
                unset($params[$param]);
            }
        }

        // Rebuild URL
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = isset($parsed['path']) ? rtrim($parsed['path'], '/') : '';
        $query = ! empty($params) ? '?' . http_build_query($params) : '';

        return strtolower($scheme . $host . $port . $path . $query);
    }
}
