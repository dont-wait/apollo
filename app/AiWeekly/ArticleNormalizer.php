<?php

namespace App\AiWeekly;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Normalizer;

class ArticleNormalizer
{
    public function canonicalUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Article URL is invalid.');
        }

        if (filter_var($parts['host'], FILTER_VALIDATE_IP) && ! filter_var($parts['host'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new InvalidArgumentException('Article URL points to a restricted address.');
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) && ! (($scheme === 'http' && $parts['port'] === 80) || ($scheme === 'https' && $parts['port'] === 443)) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $query = array_filter(explode('&', $parts['query'] ?? ''), function (string $pair): bool {
            $key = strtolower(urldecode(explode('=', $pair, 2)[0]));

            return $pair !== '' && ! collect(config('ai_weekly.tracking_query_prefixes'))
                ->contains(fn (string $prefix): bool => str_starts_with($key, strtolower($prefix)));
        });
        sort($query, SORT_STRING);

        return $scheme.'://'.$host.$port.$path.($query ? '?'.implode('&', $query) : '');
    }

    public function titleFingerprint(string $title): string
    {
        $normalized = Normalizer::normalize(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'), Normalizer::FORM_C);

        return hash('sha256', Str::lower(trim(preg_replace('/\s+/u', ' ', $normalized))));
    }
}
