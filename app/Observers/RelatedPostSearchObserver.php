<?php

namespace App\Observers;

use App\Jobs\SyncPostSearch;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;
use WeakMap;

class RelatedPostSearchObserver
{
    /** @var WeakMap<Category|Tag, list<int>>|null */
    private ?WeakMap $deletingPosts = null;

    public function saved(Category|Tag $model): void
    {
        if ($model->wasChanged(['name', 'slug', 'status'])) {
            $this->syncPosts($model);
        }
    }

    public function deleting(Category|Tag $model): void
    {
        $this->deletingPosts ??= new WeakMap;
        $this->deletingPosts[$model] = $model->posts()->pluck('posts.id')->all();
    }

    public function deleted(Category|Tag $model): void
    {
        foreach ($this->deletingPosts[$model] ?? [] as $postId) {
            SyncPostSearch::dispatch($postId)->afterCommit();
        }
        unset($this->deletingPosts[$model]);
    }

    private function syncPosts(Category|Tag $model): void
    {
        $model->posts()->select('posts.id')->chunkById(100, function (Collection $posts): void {
            foreach ($posts as $post) {
                SyncPostSearch::dispatch($post->id)->afterCommit();
            }
        }, 'posts.id', 'id');
    }
}
