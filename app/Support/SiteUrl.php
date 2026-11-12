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
}
