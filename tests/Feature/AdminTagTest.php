<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTagTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_tags(): void
    {
        $response = $this->get('/admin/tags');

        $response->assertRedirect('/login');
    }

    public function test_normal_user_cannot_access_admin_tags(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/admin/tags');

        $response->assertForbidden();
    }

    public function test_guest_cannot_mutate_tags(): void
    {
        $tag = Tag::factory()->create();

        $this->post('/admin/tags', [
            'name' => 'Laravel',
            'slug' => 'laravel',
        ])->assertRedirect('/login');

        $this->put("/admin/tags/{$tag->id}", [
            'name' => 'Updated',
            'slug' => 'updated',
        ])->assertRedirect('/login');

        $this->delete("/admin/tags/{$tag->id}")
            ->assertRedirect('/login');
    }

    public function test_normal_user_cannot_mutate_tags(): void
    {
        $user = User::factory()->create();

        $tag = Tag::factory()->create();

        $this->actingAs($user)
            ->postJson('/admin/tags', [
                'name' => 'Laravel',
                'slug' => 'laravel',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->putJson("/admin/tags/{$tag->id}", [
                'name' => 'Updated',
                'slug' => 'updated',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->deleteJson("/admin/tags/{$tag->id}")
            ->assertForbidden();
    }

    public function test_admin_can_view_tag_list(): void
    {
        $admin = User::factory()->admin()->create();

        Tag::factory()->create([
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get('/admin/tags');

        $response->assertOk();

        $response->assertJsonFragment([
            'name' => 'Laravel',
            'slug' => 'laravel',
            'posts_count' => 0,
        ]);
    }

    public function test_admin_can_view_tag_detail(): void
    {
        $admin = User::factory()->admin()->create();

        $tag = Tag::factory()->create([
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);

        $post = Post::factory()->create([
            'title' => 'Laravel Article',
            'slug' => 'laravel-article',
        ]);

        $post->tags()->attach($tag->id);

        $response = $this
            ->actingAs($admin)
            ->get("/admin/tags/{$tag->id}");

        $response->assertOk();

        $response->assertJsonFragment([
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);

        $response->assertJsonFragment([
            'title' => 'Laravel Article',
        ]);
    }

    public function test_admin_can_create_tag(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this
            ->actingAs($admin)
            ->postJson('/admin/tags', [
                'name' => 'Laravel',
                'slug' => 'laravel',
            ]);

        $response->assertCreated();

        $response->assertJson([
            'reused' => false,
        ]);

        $this->assertDatabaseHas('tags', [
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);
    }

    public function test_existing_identical_tag_is_reused(): void
    {
        $admin = User::factory()->admin()->create();

        $tag = Tag::factory()->create([
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson('/admin/tags', [
                'name' => 'Laravel',
                'slug' => 'laravel',
            ]);

        $response->assertOk();

        $response->assertJson([
            'reused' => true,
            'data' => [
                'id' => $tag->id,
            ],
        ]);

        $this->assertDatabaseCount('tags', 1);
    }

    public function test_duplicate_name_with_different_slug_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        Tag::factory()->create([
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson('/admin/tags', [
                'name' => 'Laravel',
                'slug' => 'laravel-framework',
            ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'name',
        ]);
    }

    public function test_duplicate_slug_with_different_name_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        Tag::factory()->create([
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson('/admin/tags', [
                'name' => 'Laravel Framework',
                'slug' => 'laravel',
            ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'slug',
        ]);
    }

    public function test_admin_can_update_tag(): void
    {
        $admin = User::factory()->admin()->create();

        $tag = Tag::factory()->create([
            'name' => 'PHP',
            'slug' => 'php',
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson("/admin/tags/{$tag->id}", [
                'name' => 'PHP 8',
                'slug' => 'php-8',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'PHP 8',
            'slug' => 'php-8',
        ]);
    }

    public function test_update_cannot_use_name_or_slug_of_another_tag(): void
    {
        $admin = User::factory()->admin()->create();

        $firstTag = Tag::factory()->create([
            'name' => 'PHP',
            'slug' => 'php',
        ]);

        $secondTag = Tag::factory()->create([
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson("/admin/tags/{$firstTag->id}", [
                'name' => $secondTag->name,
                'slug' => $secondTag->slug,
            ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'name',
            'slug',
        ]);
    }

    public function test_admin_can_delete_unused_tag(): void
    {
        $admin = User::factory()->admin()->create();

        $tag = Tag::factory()->create([
            'name' => 'Unused',
            'slug' => 'unused',
        ]);

        $response = $this
            ->actingAs($admin)
            ->deleteJson("/admin/tags/{$tag->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('tags', [
            'id' => $tag->id,
        ]);
    }

    public function test_tag_attached_to_post_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();

        $tag = Tag::factory()->create([
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);

        $post = Post::factory()->create();

        $post->tags()->attach($tag->id);

        $response = $this
            ->actingAs($admin)
            ->deleteJson("/admin/tags/{$tag->id}");

        $response->assertStatus(409);

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
        ]);

        $this->assertDatabaseHas('post_tags', [
            'post_id' => $post->id,
            'tag_id' => $tag->id,
        ]);
    }
}
