# Changelog

All notable changes to `sitemap` will be documented in this file.

## v2.1.0 - 2026-02-05

### What's Changed

#### New Features

- **Crawler error logging**: When the sitemap crawler encounters non-200 responses (like 500 errors) or request failures, it now:
  - Logs errors to Laravel's log with full context (URL, status code, error message, referring page)
  - Displays a grouped summary of failed URLs in the console after sitemap generation completes
  - Continues generating the sitemap instead of failing completely
  

#### CI Improvements

- Simplified test matrix for more reliable CI runs

**Full Changelog**: https://github.com/daikazu/sitemap/compare/v2.0.0...v2.1.0

## v.1.0.1 - 2025-05-14

Fix: Added RegenerateSitemapCommand to the list of commands in SitemapServiceProvider.
