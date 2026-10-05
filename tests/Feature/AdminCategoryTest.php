<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->get('/admin/categories');

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
