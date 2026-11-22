<?php

namespace App\Support;

class SiteUrl
{
    /** @param array<string, mixed> $parameters */
    public static function route(string $name, array $parameters = []): string
    {
        return rtrim((string) config('app.url'), '/').route($name, $parameters, false);
    }

    public static function article(string $slug): string
    {
        return self::route('articles.show', ['article' => $slug]);
    }

    public static function canonical(string $slug, ?string $canonicalUrl): string
    {
        return filled($canonicalUrl) ? $canonicalUrl : self::article($slug);
    }

    // A sitemap must not list a URL from another origin.
    public static function sitemapArticle(string $slug, ?string $canonicalUrl): ?string
    {
        $url = self::canonical($slug, $canonicalUrl);
        $site = parse_url((string) config('app.url'));
        $target = parse_url($url);

        if (! is_array($site) || ! is_array($target)
            || ! in_array(strtolower((string) ($target['scheme'] ?? '')), ['http', 'https'], true)
            || strtolower((string) ($site['scheme'] ?? '')) !== strtolower((string) ($target['scheme'] ?? ''))
            || strtolower((string) ($site['host'] ?? '')) !== strtolower((string) ($target['host'] ?? ''))
            || ($site['port'] ?? null) !== ($target['port'] ?? null)
            || isset($target['user']) || isset($target['pass'])) {
            return null;
        }

        return $url;
    }
}
