<?php

namespace Tests\Feature;

use App\Models\Post;
use DOMDocument;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class HomeFeedBrowserTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_published_article_card_is_visible_in_a_real_browser(): void
    {
        $chrome = (new ExecutableFinder)->find('google-chrome')
            ?? (new ExecutableFinder)->find('chromium')
            ?? (new ExecutableFinder)->find('chromium-browser');
        if ($chrome === null) {
            $this->markTestSkipped('Install Chrome or Chromium to run the home feed browser regression.');
        }
        $this->assertFileExists(public_path('build/manifest.json'), 'Run npm run build before the browser test.');
        $article = Post::factory()->published()->create(['title' => 'Browser-visible published article']);
        Post::factory()->create(['title' => 'Draft must not become a card']);
        $response = $this->get(route('home'))->assertOk();
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        $javascript = file_get_contents(public_path('build/'.$manifest['resources/js/app.js']['file']));
        $css = file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));
        $html = preg_replace('/<link\b[^>]*>|<script\b[^>]*src=[^>]*><\/script>/i', '', $response->getContent());
        $probe = <<<'JS'
            window.addEventListener('load', () => {
                window.setTimeout(() => {
                    const cards = [...document.querySelectorAll('#articles article.home-feed-card')];
                    const results = cards.map(card => ({
                        title: card.querySelector('h3 a')?.textContent,
                        url: card.querySelector('h3 a')?.href,
                        visible: card.getBoundingClientRect().height > 0
                            && getComputedStyle(card).display !== 'none'
                            && getComputedStyle(card).visibility !== 'hidden'
                            && Number(getComputedStyle(card).opacity) > 0,
                    }));
                    const output = document.createElement('pre');
                    output.id = 'browser-card-results';
                    output.textContent = JSON.stringify(results);
                    document.body.append(output);
                }, 1200);
            });
            JS;
        $html = str_replace('</head>', '<style>'.$css.'</style></head>', $html);
        $html = str_replace('</body>', '<script type="module">'.str_ireplace('</script', '<\/script', $javascript).'</script><script>'.$probe.'</script></body>', $html);
        $temporaryFile = tempnam(is_dir('/tmp/opencode') ? '/tmp/opencode' : sys_get_temp_dir(), 'apollo-browser-');
        $fixture = $temporaryFile.'.html';
        rename($temporaryFile, $fixture);
        file_put_contents($fixture, $html);

        try {
            $browser = new Process([
                $chrome, '--headless', '--no-sandbox', '--disable-dev-shm-usage',
                '--disable-gpu', '--no-first-run', '--no-default-browser-check',
                '--force-prefers-reduced-motion',
                '--virtual-time-budget=4000', '--dump-dom', 'file://'.$fixture,
            ]);
            $browser->setTimeout(30);
            $browser->mustRun();
            $document = new DOMDocument;
            @$document->loadHTML($browser->getOutput());
            $output = $document->getElementById('browser-card-results');
            $this->assertNotNull($output, 'Browser did not execute the rendering probe.');
            $cards = json_decode($output->textContent, true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame([
                ['title' => $article->title, 'url' => route('articles.show', $article->slug), 'visible' => true],
            ], $cards);
        } finally {
            unlink($fixture);
        }
    }
}
