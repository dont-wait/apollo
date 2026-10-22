<?php

namespace App\AiWeekly;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Models\WeeklyReview;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DraftPostCreator
{
    public function create(WeeklyReview $review, User $author, bool $markReviewed = true): Post
    {
        return DB::transaction(function () use ($review, $author, $markReviewed): Post {
            $locked = WeeklyReview::query()->lockForUpdate()->findOrFail($review->id);
            $existing = Post::withTrashed()->where('weekly_review_id', $locked->id)->first();
            if ($existing) {
                return $existing;
            }
            if (! in_array($locked->status, ['DRAFT', 'REVIEW'], true) || ! $locked->sourceArticles()->exists()) {
                throw new DomainException('Review chưa hợp lệ để chuyển.');
            }

            $category = $locked->suggestedCategory;
            $post = Post::create([
                'author_id' => $author->id, 'weekly_review_id' => $locked->id,
                'category_id' => $category?->status === 'ACTIVE' ? $category->id : null,
                'title' => $locked->title, 'slug' => Str::limit(Str::slug($locked->title), 200, '').'-weekly-'.$locked->id,
                'excerpt' => $locked->excerpt, 'markdown_content' => $locked->markdown_content,
                'status' => 'DRAFT', 'source_type' => 'AI_WEEKLY',
            ]);
            $post->tags()->sync(Tag::query()->whereIn('name', $locked->suggested_tags ?? [])->pluck('id'));
            $attributes = ['status' => 'CONVERTED'];
            if ($markReviewed) {
                $attributes += ['reviewed_by' => $author->id, 'reviewed_at' => now()];
            }
            $locked->update($attributes);

            return $post;
        });
    }
}
