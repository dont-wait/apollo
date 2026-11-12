@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @if ($includeHome)
        <url>
            <loc>{{ \App\Support\SiteUrl::route('home') }}</loc>
        </url>
    @endif
    @foreach ($articles as $article)
        <url>
            <loc>{{ \App\Support\SiteUrl::article($article->slug) }}</loc>
        </url>
    @endforeach
</urlset>
