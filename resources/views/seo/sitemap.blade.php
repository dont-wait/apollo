@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ route('home') }}</loc>
    </url>
    @foreach ($articles as $article)
        <url>
            <loc>{{ $article->canonical_url ?: route('articles.show', ['article' => $article->slug]) }}</loc>
        </url>
    @endforeach
</urlset>
