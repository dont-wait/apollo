<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Support\Moderation\ToxicWordFilter;
use App\Support\Moderation\ToxicWordMatch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Signature('toxic-words:scan {--scope=* : post, tag, category, comment, user_name} {--limit=500 : Maximum records per scope} {--since= : Only records created on or after this date}')]
#[Description('Scan existing content for toxic words without changing anything')]
class ScanToxicWords extends Command
{
    public function handle(ToxicWordFilter $filter): int
    {
        $scopes = $this->option('scope');
        $scopes = $scopes === [] ? ['post', 'tag', 'category', 'comment', 'user_name'] : $scopes;
        $limit = max(1, (int) $this->option('limit'));
        $since = $this->option('since');

        if ($since !== null) {
            try {
                $since = Carbon::parse($since);
            } catch (\Throwable) {
                $this->error('Invalid --since value.');

                return self::FAILURE;
            }
        }

        $rows = [];

        foreach ($scopes as $scope) {
            $rows = [...$rows, ...match ($scope) {
                'post' => $this->scanPosts($filter, $limit, $since),
                'tag' => $this->scanTags($filter, $limit, $since),
                'category' => $this->scanCategories($filter, $limit, $since),
                'comment' => $this->scanComments($filter, $limit, $since),
                'user_name' => $this->scanUsers($filter, $limit, $since),
                default => $this->invalidScope($scope),
            }];
        }

        if ($rows === []) {
            $this->info('No toxic words found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Scope', 'Field', 'Term ID', 'Term', 'Record ID', 'Title / Name'],
            $rows,
        );

        return self::SUCCESS;
    }

    /** @return list<array<int, int|string>> */
    private function scanPosts(ToxicWordFilter $filter, int $limit, ?Carbon $since): array
    {
        return $this->scan(
            Post::query()->select(['id', 'title', 'slug', 'excerpt', 'markdown_content', 'created_at']),
            $filter,
            'post',
            $limit,
            $since,
            fn (Post $post): array => [
                'title' => ['value' => (string) $post->title, 'scope' => 'post'],
                'slug' => ['value' => (string) $post->slug, 'scope' => 'post'],
                'excerpt' => ['value' => (string) $post->excerpt, 'scope' => 'post'],
                'markdown_content' => ['value' => (string) $post->markdown_content, 'scope' => 'post'],
            ],
            fn (Post $post): string => (string) $post->title,
        );
    }

    /** @return list<array<int, int|string>> */
    private function scanTags(ToxicWordFilter $filter, int $limit, ?Carbon $since): array
    {
        return $this->scan(
            Tag::query()->select(['id', 'name', 'slug', 'created_at']),
            $filter,
            'tag',
            $limit,
            $since,
            fn (Tag $tag): array => [
                'name' => ['value' => (string) $tag->name, 'scope' => 'tag'],
                'slug' => ['value' => (string) $tag->slug, 'scope' => 'tag'],
            ],
            fn (Tag $tag): string => (string) $tag->name,
        );
    }

    /** @return list<array<int, int|string>> */
    private function scanCategories(ToxicWordFilter $filter, int $limit, ?Carbon $since): array
    {
        return $this->scan(
            Category::query()->select(['id', 'name', 'slug', 'description', 'created_at']),
            $filter,
            'category',
            $limit,
            $since,
            fn (Category $category): array => [
                'name' => ['value' => (string) $category->name, 'scope' => 'category'],
                'slug' => ['value' => (string) $category->slug, 'scope' => 'category'],
                'description' => ['value' => (string) $category->description, 'scope' => 'category'],
            ],
            fn (Category $category): string => (string) $category->name,
        );
    }

    /** @return list<array<int, int|string>> */
    private function scanComments(ToxicWordFilter $filter, int $limit, ?Carbon $since): array
    {
        return $this->scan(
            Comment::query()->select(['id', 'content', 'created_at']),
            $filter,
            'comment',
            $limit,
            $since,
            fn (Comment $comment): array => [
                'content' => ['value' => (string) $comment->content, 'scope' => 'comment'],
            ],
            fn (Comment $comment): string => Str::limit((string) $comment->content, 60),
        );
    }

    /** @return list<array<int, int|string>> */
    private function scanUsers(ToxicWordFilter $filter, int $limit, ?Carbon $since): array
    {
        return $this->scan(
            User::query()->select(['id', 'name', 'created_at']),
            $filter,
            'user_name',
            $limit,
            $since,
            fn (User $user): array => [
                'name' => ['value' => (string) $user->name, 'scope' => 'user_name'],
            ],
            fn (User $user): string => (string) $user->name,
        );
    }

    /**
     * @param  Builder<*>  $query
     * @param  callable(object): array<string, array{value: string, scope: string}>  $fields
     * @param  callable(object): string  $title
     * @return list<array<int, int|string>>
     */
    private function scan(
        Builder $query,
        ToxicWordFilter $filter,
        string $scope,
        int $limit,
        ?Carbon $since,
        callable $fields,
        callable $title,
    ): array {
        $rows = [];

        $query->when($since !== null, fn (Builder $builder) => $builder->where('created_at', '>=', $since))
            ->lazyById(100)
            ->take($limit)
            ->each(function (object $record) use ($filter, $scope, $fields, $title, &$rows): void {
                foreach ($fields($record) as $field => $definition) {
                    if ($definition['value'] === '') {
                        continue;
                    }

                    $matches = $filter->find($definition['value'], $definition['scope']);

                    foreach ($matches as $match) {
                        /** @var ToxicWordMatch $match */
                        $rows[] = [
                            $record->id,
                            $scope,
                            $field,
                            $match->termId,
                            Str::limit($match->matched, 40),
                            $record->id,
                            Str::limit($title($record), 60),
                        ];
                    }
                }
            });

        return $rows;
    }

    /** @return list<array<int, int|string>> */
    private function invalidScope(string $scope): array
    {
        $this->warn('Unknown scope ignored: '.$scope);

        return [];
    }
}
