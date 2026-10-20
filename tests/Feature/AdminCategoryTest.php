<?php

namespace Tests\Feature;

use App\Jobs\SyncPostSearch;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_categories(): void
    {
        $response = $this->get('/admin/categories');

        $response->assertRedirect('/login');
    }

    public function test_normal_user_cannot_access_admin_categories(): void
    {
        $user = User::factory()->create([
            'role' => 'USER',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/admin/categories');

        $response->assertForbidden();
    }

    public function test_admin_can_view_category_list(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        Category::create([
            'name' => 'Artificial Intelligence',
            'slug' => 'artificial-intelligence',
            'status' => 'ACTIVE',
        ]);

        $response = $this
            ->actingAs($admin)
            ->getJson('/admin/categories');

        $response->assertOk();

        $response->assertJsonFragment([
            'name' => 'Artificial Intelligence',
            'slug' => 'artificial-intelligence',
        ]);
    }

    public function test_admin_can_open_category_management_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        Category::create([
            'name' => 'Artificial Intelligence',
            'slug' => 'artificial-intelligence',
            'status' => 'ACTIVE',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get('/admin/categories');

        $response->assertOk();
        $response->assertSee('Artificial Intelligence');
        $response->assertSee('Categories &amp; Tags', false);
    }

    public function test_category_post_counts_include_direct_posts_and_exclude_deleted_and_child_posts(): void
    {
        Queue::fake([SyncPostSearch::class]);
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $category = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $category->id]);
        $empty = Category::factory()->create();
        Post::factory()->create(['author_id' => $admin->id, 'category_id' => $category->id]);
        Post::factory()->published()->create(['author_id' => $admin->id, 'category_id' => $category->id]);
        Post::factory()->create(['author_id' => $admin->id, 'category_id' => $category->id])->delete();
        Post::factory()->published()->create(['author_id' => $admin->id, 'category_id' => $child->id]);

        $response = $this->actingAs($admin)->getJson('/admin/categories')->assertOk();

        $categories = collect($response->json('data'))->keyBy('id');
        $this->assertSame(2, $categories[$category->id]['posts_count']);
        $this->assertSame(1, $categories[$category->id]['children_count']);
        $this->assertSame(1, $categories[$child->id]['posts_count']);
        $this->assertSame(0, $categories[$empty->id]['posts_count']);
    }

    public function test_category_page_receives_post_counts_for_rendering(): void
    {
        Queue::fake([SyncPostSearch::class]);
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $category = Category::factory()->create();
        Post::factory()->create(['author_id' => $admin->id, 'category_id' => $category->id]);

        $this->actingAs($admin)->get('/admin/categories')
            ->assertOk()
            ->assertViewHas('categories', fn ($categories): bool => $categories->firstWhere('id', $category->id)->posts_count === 1);
    }

    public function test_admin_can_create_category(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson('/admin/categories', [
                'name' => 'Machine Learning',
                'slug' => 'machine-learning',
                'description' => 'Các bài viết về Machine Learning',
                'status' => 'ACTIVE',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('categories', [
            'name' => 'Machine Learning',
            'slug' => 'machine-learning',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_category_slug_must_be_unique(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        Category::create([
            'name' => 'AI',
            'slug' => 'ai',
            'status' => 'ACTIVE',
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson('/admin/categories', [
                'name' => 'Another AI',
                'slug' => 'ai',
            ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'slug',
        ]);
    }

    public function test_admin_can_update_category(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $category = Category::create([
            'name' => 'AI',
            'slug' => 'ai',
            'status' => 'ACTIVE',
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson("/admin/categories/{$category->id}", [
                'name' => 'Artificial Intelligence',
                'slug' => 'artificial-intelligence',
                'description' => 'AI articles',
                'status' => 'ACTIVE',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Artificial Intelligence',
            'slug' => 'artificial-intelligence',
        ]);
    }

    public function test_category_cannot_be_its_own_parent(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $category = Category::create([
            'name' => 'AI',
            'slug' => 'ai',
            'status' => 'ACTIVE',
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson("/admin/categories/{$category->id}", [
                'parent_id' => $category->id,
                'name' => 'AI',
                'slug' => 'ai',
                'status' => 'ACTIVE',
            ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'parent_id',
        ]);
    }

    public function test_admin_can_deactivate_category(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $category = Category::create([
            'name' => 'AI',
            'slug' => 'ai',
            'status' => 'ACTIVE',
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson("/admin/categories/{$category->id}", [
                'name' => 'AI',
                'slug' => 'ai',
                'status' => 'INACTIVE',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'status' => 'INACTIVE',
        ]);
    }

    public function test_null_status_defaults_to_active_when_creating_category(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson('/admin/categories', [
                'name' => 'Machine Learning',
                'slug' => 'machine-learning',
                'status' => null,
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('categories', [
            'slug' => 'machine-learning',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_category_cannot_use_its_descendant_as_parent(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
        ]);

        $parent = Category::create([
            'name' => 'Parent',
            'slug' => 'parent',
            'status' => 'ACTIVE',
        ]);

        $child = Category::create([
            'parent_id' => $parent->id,
            'name' => 'Child',
            'slug' => 'child',
            'status' => 'ACTIVE',
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson("/admin/categories/{$parent->id}", [
                'parent_id' => $child->id,
                'name' => 'Parent',
                'slug' => 'parent',
                'status' => 'ACTIVE',
            ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'parent_id',
        ]);
    }
}
