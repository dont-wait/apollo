<?php

namespace Database\Seeders;

use App\Models\AiSource;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogContentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', 'ADMIN')->firstOrFail();

        $categories = collect([
            ['name' => 'Artificial Intelligence', 'slug' => 'artificial-intelligence'],
            ['name' => 'Developer Tools', 'slug' => 'developer-tools'],
            ['name' => 'Future Systems', 'slug' => 'future-systems'],
        ])->mapWithKeys(fn (array $category): array => [
            $category['slug'] => Category::query()->firstOrCreate(
                ['slug' => $category['slug']],
                ['name' => $category['name'], 'status' => 'ACTIVE'],
            ),
        ]);

        $tags = collect(['AI', 'Engineering', 'Research', 'Product'])->map(
            fn (string $name): Tag => Tag::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            ),
        );

        AiSource::query()->firstOrCreate(
            ['name' => 'Apollo Research Feed'],
            ['source_type' => 'RSS', 'site_url' => 'https://example.com', 'feed_url' => 'https://example.com/feed.xml', 'trust_score' => 8, 'priority' => 1, 'status' => 'ACTIVE'],
        );

        foreach ([
            ['slug' => 'the-new-shape-of-ai-workflows', 'title' => 'The New Shape of AI Workflows', 'category' => 'artificial-intelligence'],
            ['slug' => 'building-calm-developer-tools', 'title' => 'Building Calm Developer Tools', 'category' => 'developer-tools'],
            ['slug' => 'systems-that-think-in-public', 'title' => 'Systems That Think in Public', 'category' => 'future-systems'],
        ] as $postData) {
            $post = Post::query()->firstOrCreate(
                ['slug' => $postData['slug']],
                [
                    'author_id' => $admin->id,
                    'category_id' => $categories[$postData['category']]->id,
                    'title' => $postData['title'],
                    'excerpt' => 'A practical field note from the Apollo Blog editorial desk.',
                    'markdown_content' => '# '.$postData['title']."\n\n".fake()->paragraphs(5, true),
                    'status' => 'PUBLISHED',
                    'source_type' => 'MANUAL',
                    'published_at' => now()->subDays(fake()->numberBetween(1, 20)),
                ],
            );

            $post->tags()->sync($tags->take(2)->pluck('id'));
        }
    }
}
