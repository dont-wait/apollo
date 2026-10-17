<?php

namespace App\AiWeekly;

use App\Models\AiSource;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SourceFetcher
{
    /** @return array<int, array<string, mixed>> */
    public function fetch(AiSource $source): array
    {
        if ($source->status !== 'ACTIVE' || $source->source_type === 'WEBSITE') {
            throw new RuntimeException('Source is inactive or has no automatic adapter.');
        }

        $url = $source->feed_url;
        $ip = $this->publicIp($url);
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?? (str_starts_with($url, 'https://') ? 443 : 80);
        $response = Http::connectTimeout(5)
            ->timeout(config('ai_weekly.http_timeout'))
            ->withHeaders($source->source_type === 'API' ? config('ai_weekly.api_adapters.'.$source->adapter_key.'.headers', []) : [])
            ->withoutRedirecting()
            ->withOptions(['proxy' => '', 'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]]])
            ->get($url);

        if (! $response->successful()) {
            $retryable = $response->status() === 429 || $response->serverError();
            $retryAfter = $response->header('Retry-After');
            $seconds = null;
            if ($retryable && $retryAfter) {
                $seconds = is_numeric($retryAfter) ? (int) $retryAfter : max(0, strtotime($retryAfter) - time());
            }
            throw new SourceRequestException($retryable, $seconds);
        }
        if (strlen($response->body()) > config('ai_weekly.max_feed_bytes')) {
            throw new RuntimeException('Source response is too large.');
        }

        if ($source->source_type === 'API') {
            $items = $response->json('items');
            if (! is_array($items)) {
                throw new RuntimeException('API response has no items array.');
            }

            return array_slice($items, 0, config('ai_weekly.max_items_per_source'));
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
            if ($xml === false) {
                throw new RuntimeException('RSS response is invalid XML.');
            }

            $entries = $xml->channel->item ?? $xml->entry;
            $items = [];
            foreach ($entries as $entry) {
                $link = (string) ($entry->link['href'] ?? $entry->link);
                $items[] = [
                    'title' => trim((string) $entry->title),
                    'url' => trim($link),
                    'summary' => trim(strip_tags((string) ($entry->description ?? $entry->summary))),
                    'content' => trim(strip_tags((string) ($entry->children('http://purl.org/rss/1.0/modules/content/')->encoded ?? $entry->content))),
                    'author' => trim((string) $entry->author) ?: null,
                    'published_at' => trim((string) ($entry->pubDate ?? $entry->published ?? $entry->updated)) ?: null,
                ];
                if (count($items) >= config('ai_weekly.max_items_per_source')) {
                    break;
                }
            }

            return $items;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function publicIp(?string $url): string
    {
        $parts = is_string($url) ? parse_url($url) : false;
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Source URL is invalid.');
        }

        $host = $parts['host'];
        if (str_contains($host, ':')) {
            throw new RuntimeException('IPv6 source hosts are not supported.');
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : gethostbynamel($host);
        if (! $addresses) {
            throw new RuntimeException('Source host cannot be resolved.');
        }

        foreach ($addresses as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Source URL resolves to a restricted address.');
            }
        }

        return $addresses[0];
    }
}
