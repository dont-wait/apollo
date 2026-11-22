@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @if ($includeHome)
        <url>
            <loc>{{ \App\Support\SiteUrl::route('home') }}</loc>
        </url>
    @endif
    @foreach ($articles as $article)
        @php $articleUrl = \App\Support\SiteUrl::sitemapArticle($article->slug, $article->canonical_url); @endphp
        @if ($articleUrl !== null)
            <url>
                <loc>{{ $articleUrl }}</loc>
            </url>
        @endif
    @endforeach
</urlset>
