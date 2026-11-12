@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ config('app.name', 'Apollo Blog') }}</title>
        <link>{{ route('home') }}</link>
        <description>Latest published articles from {{ config('app.name', 'Apollo Blog') }}.</description>
        <language>{{ str_replace('_', '-', app()->getLocale()) }}</language>
        <atom:link href="{{ route('rss') }}" rel="self" type="application/rss+xml" />
        @foreach ($articles as $article)
            @php $articleUrl = $article->canonical_url ?: route('articles.show', ['article' => $article->slug]); @endphp
            <item>
                <title>{{ $article->seo_title ?: $article->title }}</title>
                <link>{{ $articleUrl }}</link>
                <guid isPermaLink="true">{{ $articleUrl }}</guid>
                @if ($article->seo_description || $article->excerpt)
                    <description>{{ $article->seo_description ?: $article->excerpt }}</description>
                @endif
                <pubDate>{{ $article->published_at->format(DATE_RSS) }}</pubDate>
            </item>
        @endforeach
    </channel>
</rss>
