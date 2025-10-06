<?php

use Daikazu\Sitemap\Commands\SitemapCommand;

describe('Hybrid Mode Deduplication', function () {
    beforeEach(function () {
        $this->command = new SitemapCommand;
        $this->reflection = new \ReflectionClass($this->command);
    });

    test('isModelUrl returns true for exact URL match', function (): void {
        // Simulate adding model URL using reflection
        $property = $this->reflection->getProperty('modelUrls');
        $property->setAccessible(true);
        $property->setValue($this->command, ['https://example.com/posts/my-post']);

        expect($this->command->isModelUrl('https://example.com/posts/my-post'))->toBeTrue();
    });

    test('isModelUrl returns false for non-model URL', function (): void {
        $property = $this->reflection->getProperty('modelUrls');
        $property->setAccessible(true);
        $property->setValue($this->command, ['https://example.com/posts/my-post']);

        expect($this->command->isModelUrl('https://example.com/about'))->toBeFalse();
    });

    test('isModelUrl handles trailing slash normalization', function (): void {
        $property = $this->reflection->getProperty('modelUrls');
        $property->setAccessible(true);
        $property->setValue($this->command, ['https://example.com/posts/my-post']);

        // Crawled URL with trailing slash should match
        expect($this->command->isModelUrl('https://example.com/posts/my-post/'))->toBeTrue();
    });

    test('isModelUrl handles URL case insensitivity', function (): void {
        $property = $this->reflection->getProperty('modelUrls');
        $property->setAccessible(true);
        $property->setValue($this->command, ['https://example.com/posts/my-post']);

        // Different case should still match
        expect($this->command->isModelUrl('HTTPS://EXAMPLE.COM/posts/my-post'))->toBeTrue();
        expect($this->command->isModelUrl('https://EXAMPLE.com/POSTS/my-post'))->toBeTrue();
    });

    test('isModelUrl ignores tracking parameters', function (): void {
        $property = $this->reflection->getProperty('modelUrls');
        $property->setAccessible(true);
        $property->setValue($this->command, ['https://example.com/posts/my-post']);

        // URLs with tracking params should match the base URL
        expect($this->command->isModelUrl('https://example.com/posts/my-post?utm_source=google'))->toBeTrue();
        expect($this->command->isModelUrl('https://example.com/posts/my-post?utm_campaign=summer'))->toBeTrue();
        expect($this->command->isModelUrl('https://example.com/posts/my-post?gclid=123456'))->toBeTrue();
        expect($this->command->isModelUrl('https://example.com/posts/my-post?fbclid=789'))->toBeTrue();
    });

    test('isModelUrl handles combined normalizations', function (): void {
        $property = $this->reflection->getProperty('modelUrls');
        $property->setAccessible(true);
        $property->setValue($this->command, ['https://example.com/posts/my-post']);

        // Combination: trailing slash + uppercase + tracking params
        expect($this->command->isModelUrl('HTTPS://EXAMPLE.COM/posts/my-post/?utm_source=facebook&fbclid=abc123'))->toBeTrue();
    });

    test('isModelUrl preserves important query parameters', function (): void {
        $property = $this->reflection->getProperty('modelUrls');
        $property->setAccessible(true);
        $property->setValue($this->command, ['https://example.com/products?category=electronics']);

        // Should match with same important params
        expect($this->command->isModelUrl('https://example.com/products?category=electronics'))->toBeTrue();

        // Should not match with different important params
        expect($this->command->isModelUrl('https://example.com/products?category=books'))->toBeFalse();
    });

    test('isModelUrl handles multiple model URLs', function (): void {
        $property = $this->reflection->getProperty('modelUrls');
        $property->setAccessible(true);
        $property->setValue($this->command, [
            'https://example.com/posts/first',
            'https://example.com/posts/second',
            'https://example.com/posts/third',
        ]);

        expect($this->command->isModelUrl('https://example.com/posts/first'))->toBeTrue();
        expect($this->command->isModelUrl('https://example.com/posts/second/'))->toBeTrue();
        expect($this->command->isModelUrl('https://example.com/posts/third?utm_source=google'))->toBeTrue();
        expect($this->command->isModelUrl('https://example.com/posts/fourth'))->toBeFalse();
    });

    test('normalizeUrl removes trailing slashes', function (): void {
        $method = $this->reflection->getMethod('normalizeUrl');
        $method->setAccessible(true);
        $normalized = $method->invokeArgs($this->command, ['https://example.com/posts/my-post/']);

        expect($normalized)->toBe('https://example.com/posts/my-post');
    });

    test('normalizeUrl converts to lowercase', function (): void {
        $method = $this->reflection->getMethod('normalizeUrl');
        $method->setAccessible(true);
        $normalized = $method->invokeArgs($this->command, ['HTTPS://EXAMPLE.COM/Posts/My-Post']);

        expect($normalized)->toBe('https://example.com/posts/my-post');
    });

    test('normalizeUrl removes tracking parameters', function (): void {
        $method = $this->reflection->getMethod('normalizeUrl');
        $method->setAccessible(true);
        $url = 'https://example.com/posts/test?utm_source=google&utm_medium=cpc&utm_campaign=summer&regular_param=keep';

        $normalized = $method->invokeArgs($this->command, [$url]);

        // Should keep regular params but remove tracking
        expect($normalized)->toContain('regular_param=keep');
        expect($normalized)->not->toContain('utm_source');
        expect($normalized)->not->toContain('utm_medium');
        expect($normalized)->not->toContain('utm_campaign');
    });

    test('normalizeUrl handles URLs without query strings', function (): void {
        $method = $this->reflection->getMethod('normalizeUrl');
        $method->setAccessible(true);
        $normalized = $method->invokeArgs($this->command, ['https://example.com/about']);

        expect($normalized)->toBe('https://example.com/about');
    });

    test('normalizeUrl handles URLs with ports', function (): void {
        $method = $this->reflection->getMethod('normalizeUrl');
        $method->setAccessible(true);
        $normalized = $method->invokeArgs($this->command, ['https://example.com:8080/posts']);

        expect($normalized)->toBe('https://example.com:8080/posts');
    });

    test('normalizeUrl removes session identifiers', function (): void {
        $method = $this->reflection->getMethod('normalizeUrl');
        $method->setAccessible(true);
        $url = 'https://example.com/posts?sessionid=abc123&PHPSESSID=xyz789&_ga=123';

        $normalized = $method->invokeArgs($this->command, [$url]);

        expect($normalized)->toBe('https://example.com/posts');
    });

    test('normalizeUrl handles root URLs', function (): void {
        $method = $this->reflection->getMethod('normalizeUrl');
        $method->setAccessible(true);
        $normalized1 = $method->invokeArgs($this->command, ['https://example.com']);
        $normalized2 = $method->invokeArgs($this->command, ['https://example.com/']);

        expect($normalized1)->toBe('https://example.com');
        expect($normalized2)->toBe('https://example.com');
        expect($normalized1)->toBe($normalized2);
    });
});
