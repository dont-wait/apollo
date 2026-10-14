<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoArticleSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', 'ADMIN')->firstOrFail();

        $categoryDefinitions = [
            'ai-systems' => 'AI Systems',
            'inference' => 'Inference & Hardware',
            'developer-tools' => 'Developer Tools',
            'distributed-systems' => 'Distributed Systems',
            'research-notes' => 'Research Notes',
        ];

        $categories = collect($categoryDefinitions)->mapWithKeys(
            fn (string $name, string $slug): array => [
                $slug => Category::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $name, 'status' => 'ACTIVE'],
                ),
            ],
        );

        $tagNames = [
            'AI',
            'Agents',
            'Architecture',
            'Benchmarks',
            'CUDA',
            'Databases',
            'Evaluation',
            'Infrastructure',
            'Observability',
            'Python',
            'RAG',
            'Rust',
            'Search',
            'Security',
            'Systems',
        ];

        $tags = collect($tagNames)->mapWithKeys(
            fn (string $name): array => [
                Str::slug($name) => Tag::query()->firstOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name],
                ),
            ],
        );

        $themes = [
            ['category' => 'ai-systems', 'tag' => 'AI', 'title' => 'Designing Reliable AI Systems', 'intro' => 'A practical field note on turning promising model behavior into a dependable product capability.'],
            ['category' => 'inference', 'tag' => 'CUDA', 'title' => 'Making Inference Faster Without Losing Clarity', 'intro' => 'A measured look at latency, memory pressure, and the engineering trade-offs behind faster inference.'],
            ['category' => 'developer-tools', 'tag' => 'Python', 'title' => 'Developer Tools That Reduce Cognitive Load', 'intro' => 'Small tools and conventions can make complex systems easier to change, debug, and explain.'],
            ['category' => 'distributed-systems', 'tag' => 'Infrastructure', 'title' => 'Distributed Systems That Fail Gracefully', 'intro' => 'Resilience is less about avoiding failure and more about making failure observable, bounded, and recoverable.'],
            ['category' => 'research-notes', 'tag' => 'Evaluation', 'title' => 'Research Notes for Better Engineering Decisions', 'intro' => 'A compact method for translating papers, experiments, and uncertain evidence into useful next steps.'],
        ];

        for ($index = 1; $index <= 50; $index++) {
            $theme = $themes[($index - 1) % count($themes)];
            $title = $theme['title'].' — Field Note '.str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $secondaryTag = $tagNames[($index + 3) % count($tagNames)];
            $tertiaryTag = $tagNames[($index + 8) % count($tagNames)];

            $post = Post::withTrashed()->updateOrCreate(
                ['slug' => 'demo-article-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)],
                [
                    'author_id' => $admin->id,
                    'category_id' => $categories[$theme['category']]->id,
                    'title' => $title,
                    'excerpt' => $theme['intro'],
                    'markdown_content' => '# '.$title
                        ."\n\n{$theme['intro']}"
                        ."\n\n## What we learned\n\n"
                        .fake()->paragraphs(2, true)
                        ."\n\n## Practical checklist\n\n"
                        ."- Keep the critical path measurable.\n"
                        ."- Make the failure mode visible before optimizing it.\n"
                        ."- Prefer small, reversible changes over speculative complexity.\n",
                    'status' => 'PUBLISHED',
                    'source_type' => 'MANUAL',
                    'published_at' => now()->subDays(50 - $index),
                    'view_count' => $index * 137,
                    'like_count' => $index * 11,
                ],
            );

            $post->tags()->sync([
                $tags[Str::slug($theme['tag'])]->id,
                $tags[Str::slug($secondaryTag)]->id,
                $tags[Str::slug($tertiaryTag)]->id,
            ]);
        }

        $this->command?->info('Seeded 50 demo published articles with categories and tags.');
    }
}
