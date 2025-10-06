<?php

use Daikazu\Sitemap\Services\ModelSitemapGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

// Test model
class TestPost extends Model
{
    protected $table = 'posts';

    protected $fillable = ['title', 'slug', 'published', 'updated_at'];

    public $timestamps = false;
}

describe('ModelSitemapGenerator', function () {
    beforeEach(function () {
        // Set up test database
        Config::set('database.default', 'testing');
        Config::set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        // Create test table
        \Illuminate\Support\Facades\Schema::create('posts', function ($table) {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->boolean('published')->default(false);
            $table->timestamp('updated_at')->useCurrent();
        });
    });

    afterEach(function () {
        \Illuminate\Support\Facades\Schema::dropIfExists('posts');
    });

    test('isEnabled returns false when mode is crawl', function (): void {
        Config::set('sitemap.generate_mode', 'crawl');

        $generator = app(ModelSitemapGenerator::class);

        expect($generator->isEnabled())->toBeFalse();
    });

    test('isEnabled returns true when mode is models', function (): void {
        Config::set('sitemap.generate_mode', 'models');

        $generator = app(ModelSitemapGenerator::class);

        expect($generator->isEnabled())->toBeTrue();
    });

    test('isEnabled returns true when mode is hybrid', function (): void {
        Config::set('sitemap.generate_mode', 'hybrid');

        $generator = app(ModelSitemapGenerator::class);

        expect($generator->isEnabled())->toBeTrue();
    });

    test('getMode returns configured mode', function (): void {
        Config::set('sitemap.generate_mode', 'hybrid');

        $generator = app(ModelSitemapGenerator::class);

        expect($generator->getMode())->toBe('hybrid');
    });

    test('generate creates URLs from configured models', function (): void {
        // Create test posts
        TestPost::create(['title' => 'First Post', 'slug' => 'first-post', 'published' => true]);
        TestPost::create(['title' => 'Second Post', 'slug' => 'second-post', 'published' => true]);
        TestPost::create(['title' => 'Draft Post', 'slug' => 'draft-post', 'published' => false]);

        Config::set('sitemap.models', [
            TestPost::class => [
                'enabled'    => true,
                'url'        => fn ($post) => 'https://example.com/posts/' . $post->slug,
                'lastmod'    => 'updated_at',
                'changefreq' => 'weekly',
                'priority'   => 0.8,
                'query'      => fn ($query) => $query->where('published', true),
            ],
        ]);

        $generator = app(ModelSitemapGenerator::class);
        $urls = $generator->generate();

        expect($urls)->toHaveCount(2);
        expect($urls[0]->url)->toBe('https://example.com/posts/first-post');
        expect($urls[1]->url)->toBe('https://example.com/posts/second-post');
    });

    test('generate skips disabled models', function (): void {
        TestPost::create(['title' => 'Test Post', 'slug' => 'test-post', 'published' => true]);

        Config::set('sitemap.models', [
            TestPost::class => [
                'enabled' => false,
                'url'     => fn ($post) => 'https://example.com/posts/' . $post->slug,
            ],
        ]);

        $generator = app(ModelSitemapGenerator::class);
        $urls = $generator->generate();

        expect($urls)->toBeEmpty();
    });

    test('generate uses closure for priority', function (): void {
        TestPost::create(['title' => 'Featured Post', 'slug' => 'featured', 'published' => true]);

        Config::set('sitemap.models', [
            TestPost::class => [
                'enabled'  => true,
                'url'      => fn ($post) => 'https://example.com/posts/' . $post->slug,
                'priority' => fn ($post) => $post->slug === 'featured' ? 0.9 : 0.7,
            ],
        ]);

        $generator = app(ModelSitemapGenerator::class);
        $urls = $generator->generate();

        expect($urls[0]->priority)->toBe(0.9);
    });

    test('generate uses closure for changefreq', function (): void {
        TestPost::create(['title' => 'Test Post', 'slug' => 'test', 'published' => true]);

        Config::set('sitemap.models', [
            TestPost::class => [
                'enabled'    => true,
                'url'        => fn ($post) => 'https://example.com/posts/' . $post->slug,
                'changefreq' => fn ($post) => 'daily',
            ],
        ]);

        $generator = app(ModelSitemapGenerator::class);
        $urls = $generator->generate();

        expect($urls[0]->changeFrequency)->toBe('daily');
    });

    test('reset clears generated URLs', function (): void {
        TestPost::create(['title' => 'Test Post', 'slug' => 'test', 'published' => true]);

        Config::set('sitemap.models', [
            TestPost::class => [
                'enabled' => true,
                'url'     => fn ($post) => 'https://example.com/posts/' . $post->slug,
            ],
        ]);

        $generator = app(ModelSitemapGenerator::class);
        $urls = $generator->generate();

        expect($urls)->toHaveCount(1);

        $generator->reset();

        expect($generator->getUrls())->toBeEmpty();
    });

    test('generate returns empty array when no models configured', function (): void {
        Config::set('sitemap.models', []);

        $generator = app(ModelSitemapGenerator::class);
        $urls = $generator->generate();

        expect($urls)->toBeEmpty();
    });
});
