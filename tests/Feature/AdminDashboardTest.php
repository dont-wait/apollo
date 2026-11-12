<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_real_editorial_and_community_statistics(): void
    {
        $this->freezeTime();

        $admin = User::factory()->admin()->create([
            'created_at' => now()->subDays(40),
        ]);
        $activeUser = User::factory()->create();
        $inactiveUser = User::factory()->create([
            'status' => User::STATUS_INACTIVE,
            'created_at' => now()->subDays(40),
        ]);
        User::factory()->create([
            'created_at' => now()->addDay(),
        ]);
        $category = Category::factory()->create(['name' => 'Research']);

        $publishedPost = Post::factory()->for($admin, 'author')->for($category)->create([
            'title' => 'Published dashboard article',
            'status' => 'PUBLISHED',
            'published_at' => now()->subDay(),
            'view_count' => 120,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subHour(),
        ]);
        $pendingPost = Post::factory()->for($admin, 'author')->for($category)->create([
            'title' => 'Pending dashboard article',
            'status' => 'REVIEW',
            'view_count' => 40,
            'updated_at' => now()->subHours(2),
        ]);
        $draftPost = Post::factory()->for($admin, 'author')->for($category)->create([
            'title' => 'Draft dashboard article',
            'status' => 'DRAFT',
            'view_count' => 8,
            'updated_at' => now()->subHours(3),
        ]);
        $scheduledPost = Post::factory()->for($admin, 'author')->for($category)->create([
            'title' => 'Scheduled dashboard article',
            'status' => 'SCHEDULED',
            'scheduled_at' => now()->addDay(),
            'updated_at' => now()->subHours(4),
        ]);

        Comment::factory()->for($publishedPost, 'post')->for($admin, 'user')->create([
            'status' => Comment::STATUS_VISIBLE,
            'created_at' => now()->subDay(),
        ]);
        Comment::factory()->for($publishedPost, 'post')->for($admin, 'user')->create([
            'status' => Comment::STATUS_PENDING,
            'created_at' => now()->subDays(2),
        ]);
        Comment::factory()->for($publishedPost, 'post')->for($admin, 'user')->create([
            'status' => Comment::STATUS_HIDDEN,
            'created_at' => now()->subDays(31),
        ]);
        Comment::factory()->for($publishedPost, 'post')->for($admin, 'user')->create([
            'status' => Comment::STATUS_VISIBLE,
            'created_at' => now()->addDay(),
        ]);

        DB::table('bookmarks')->insert([
            ['user_id' => $activeUser->id, 'post_id' => $publishedPost->id, 'created_at' => now()],
            ['user_id' => $inactiveUser->id, 'post_id' => $publishedPost->id, 'created_at' => now()],
            ['user_id' => $activeUser->id, 'post_id' => $pendingPost->id, 'created_at' => now()],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('postStats', fn (array $stats): bool => $stats === [
                'total' => 4,
                'draft' => 1,
                'pending' => 1,
                'scheduled' => 1,
                'published' => 1,
                'total_views' => 168,
            ])
            ->assertViewHas('commentStats', fn (array $stats): bool => $stats === [
                'total' => 4,
                'recent' => 2,
                'pending' => 1,
                'hidden' => 1,
            ])
            ->assertViewHas('userStats', fn (array $stats): bool => $stats === [
                'total' => 4,
                'recent' => 1,
                'active' => 3,
                'inactive' => 1,
            ])
            ->assertViewHas('totalBookmarks', 3)
            ->assertViewHas('recentPosts', fn ($posts): bool => $posts->modelKeys() === [
                $publishedPost->id,
                $pendingPost->id,
                $draftPost->id,
                $scheduledPost->id,
            ])
            ->assertViewHas('topViewedPosts', fn ($posts): bool => $posts->modelKeys() === [
                $publishedPost->id,
                $pendingPost->id,
                $draftPost->id,
                $scheduledPost->id,
            ])
            ->assertViewHas('topBookmarkedPosts', fn ($posts): bool => $posts->modelKeys() === [
                $publishedPost->id,
                $pendingPost->id,
            ])
            ->assertSeeText('Research')
            ->assertSeeText($admin->name)
            ->assertSeeText('Chờ duyệt')
            ->assertSee('href="'.route('articles.show', ['article' => $publishedPost->slug]).'"', false)
            ->assertSee('href="'.route('admin.articles.edit', $draftPost).'"', false);
    }

    public function test_admin_dashboard_shows_zero_counts_when_there_is_no_content(): void
    {
        $this->freezeTime();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('postStats', fn (array $stats): bool => $stats === [
                'total' => 0,
                'draft' => 0,
                'pending' => 0,
                'scheduled' => 0,
                'published' => 0,
                'total_views' => 0,
            ])
            ->assertViewHas('commentStats', fn (array $stats): bool => $stats === [
                'total' => 0,
                'recent' => 0,
                'pending' => 0,
                'hidden' => 0,
            ])
            ->assertViewHas('userStats', fn (array $stats): bool => $stats === [
                'total' => 1,
                'recent' => 1,
                'active' => 1,
                'inactive' => 0,
            ])
            ->assertViewHas('totalBookmarks', 0)
            ->assertSeeText('Chưa có bài viết nào.')
            ->assertSeeText('Chưa có bài viết được lưu.');
    }
}
