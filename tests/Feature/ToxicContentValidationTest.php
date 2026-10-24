<?php

namespace Tests\Feature;

use App\Models\AiArticle;
use App\Models\Post;
use App\Models\ToxicWord;
use App\Models\User;
use App\Models\WeeklyReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToxicContentValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_article_create_is_blocked_and_returns_highlight_payload(): void
    {
        $admin = User::factory()->admin()->create();
        $this->word('badword', 'post');

        $this->actingAs($admin)
            ->from(route('admin.articles.create'))
            ->post(route('admin.articles.store'), [
                'title' => 'A badword title',
                'markdown_content' => 'Clean body',
                'status' => 'DRAFT',
            ])
            ->assertRedirect(route('admin.articles.create'))
            ->assertSessionHasErrors('title');

        $this->assertDatabaseMissing('posts', ['title' => 'A badword title']);

        $moderation = session('moderation');

        $this->assertSame(1, $moderation['fields']['title']['count']);
        $this->assertStringContainsString('<mark', $moderation['fields']['title']['snippets'][0]);
    }

    public function test_admin_article_markdown_violation_returns_overlay_and_preview_html(): void
    {
        $admin = User::factory()->admin()->create();
        $this->word('badword', 'post');

        $this->actingAs($admin)
            ->from(route('admin.articles.create'))
            ->post(route('admin.articles.store'), [
                'title' => 'Clean title',
                'markdown_content' => "# Heading\n\nThis has **badword** in it.",
                'status' => 'DRAFT',
            ])
            ->assertSessionHasErrors('markdown_content');

        $moderation = session('moderation');
        $field = $moderation['fields']['markdown_content'];

        $this->assertArrayHasKey('overlay_html', $field);
        $this->assertArrayHasKey('preview_html', $field);
        $this->assertSame(22, $field['first_offset']);
        $this->assertStringContainsString('toxic-word-hit', $field['overlay_html']);
        $this->assertStringContainsString('<strong>', $field['preview_html']);
        $this->assertStringContainsString('<mark class="toxic-word-hit"', $field['preview_html']);
    }

    public function test_markdown_formatting_cannot_split_a_toxic_word(): void
    {
        $admin = User::factory()->admin()->create();
        $this->word('badword', 'post');

        $this->actingAs($admin)
            ->from(route('admin.articles.create'))
            ->post(route('admin.articles.store'), [
                'title' => 'Clean title',
                'markdown_content' => 'This has bad**word** in it.',
                'status' => 'DRAFT',
            ])
            ->assertSessionHasErrors('markdown_content');

        $moderation = session('moderation');
        $field = $moderation['fields']['markdown_content'];

        $this->assertSame(1, $field['count']);
        $this->assertArrayNotHasKey('overlay_html', $field);
        $this->assertStringContainsString('<mark class="toxic-word-hit"', $field['preview_html']);
        $this->assertDatabaseMissing('posts', ['title' => 'Clean title']);
    }

    public function test_markdown_first_offset_uses_javascript_utf16_units(): void
    {
        $admin = User::factory()->admin()->create();
        $this->word('badword', 'post');

        $text = "Ảnh đẹp\n\nMột badword ở đây.";
        $byteOffset = strpos($text, 'badword');
        $prefix = substr($text, 0, $byteOffset);
        $expectedOffset = intdiv(strlen(mb_convert_encoding($prefix, 'UTF-16LE', 'UTF-8')), 2);

        $this->actingAs($admin)
            ->from(route('admin.articles.create'))
            ->post(route('admin.articles.store'), [
                'title' => 'Clean title',
                'markdown_content' => $text,
                'status' => 'DRAFT',
            ])
            ->assertSessionHasErrors('markdown_content');

        $field = session('moderation')['fields']['markdown_content'];

        $this->assertSame($expectedOffset, $field['first_offset']);
        $this->assertNotSame($byteOffset, $expectedOffset);
    }

    public function test_admin_article_update_is_blocked(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Post::factory()->create(['title' => 'Original']);
        $this->word('badword', 'post');

        $this->actingAs($admin)
            ->from(route('admin.articles.edit', $article))
            ->put(route('admin.articles.update', $article), [
                'title' => 'badword replacement',
                'markdown_content' => 'Clean body',
                'status' => 'DRAFT',
            ])
            ->assertSessionHasErrors('title');

        $this->assertSame('Original', $article->fresh()->title);
    }

    public function test_admin_tag_create_is_blocked(): void
    {
        $admin = User::factory()->admin()->create();
        $this->word('badword', 'tag');

        $this->actingAs($admin)
            ->postJson(route('admin.tags.store'), [
                'name' => 'badword tag',
                'slug' => 'badword-tag',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name')
            ->assertJsonMissingPath('moderation');

        $this->assertDatabaseMissing('tags', ['name' => 'badword tag']);
    }

    public function test_admin_category_create_is_blocked(): void
    {
        $admin = User::factory()->admin()->create();
        $this->word('badword', 'category');

        $this->actingAs($admin)
            ->postJson(route('admin.categories.store'), [
                'name' => 'badword category',
                'slug' => 'badword-category',
                'status' => 'ACTIVE',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseMissing('categories', ['name' => 'badword category']);
    }

    public function test_public_comment_is_blocked_without_highlight_payload(): void
    {
        $user = User::factory()->create();
        $article = Post::factory()->published()->create();
        $this->word('badword', 'comment');

        $response = $this->actingAs($user)
            ->postJson(route('articles.comments.store', ['article' => $article->slug]), [
                'content' => 'This is a badword comment',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');

        $this->assertArrayNotHasKey('moderation', $response->json());
        $response->assertDontSee('badword');
        $this->assertDatabaseMissing('comments', ['content' => 'This is a badword comment']);
    }

    public function test_registration_is_blocked_for_a_toxic_display_name(): void
    {
        $this->word('badword', 'user_name');
        $password = fake()->password(minLength: 12);

        $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'badword user',
                'email' => 'badword@example.com',
                'password' => $password,
                'password_confirmation' => $password,
            ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('name');

        $oldInput = session('_old_input', []);

        $this->assertArrayNotHasKey('password', $oldInput);
        $this->assertArrayNotHasKey('password_confirmation', $oldInput);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'badword@example.com']);
    }

    public function test_generated_new_tag_slug_is_moderated(): void
    {
        $admin = User::factory()->admin()->create();
        $this->word('bad-word', 'tag');

        $this->actingAs($admin)
            ->from(route('admin.articles.create'))
            ->post(route('admin.articles.store'), [
                'title' => 'Clean title',
                'markdown_content' => 'Clean body',
                'new_tags' => ['Bad Word'],
                'status' => 'DRAFT',
            ])
            ->assertSessionHasErrors('new_tag_slugs');

        $this->assertDatabaseMissing('posts', ['title' => 'Clean title']);
        $this->assertDatabaseMissing('tags', ['name' => 'Bad Word']);
    }

    public function test_admin_user_create_is_blocked_for_a_toxic_display_name(): void
    {
        $admin = User::factory()->admin()->create();
        $this->word('badword', 'user_name');

        $this->actingAs($admin)
            ->from(route('admin.users.create'))
            ->post(route('admin.users.store'), [
                'name' => 'badword user',
                'email' => 'badword-user@example.com',
                'password' => 'secure-password',
                'password_confirmation' => 'secure-password',
                'role' => User::ROLE_USER,
                'status' => User::STATUS_ACTIVE,
            ])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('users', ['email' => 'badword-user@example.com']);
    }

    public function test_weekly_review_update_is_blocked_with_highlight_payload(): void
    {
        $admin = User::factory()->admin()->create();
        $review = WeeklyReview::factory()->create(['status' => 'REVIEW']);
        $originalTitle = $review->title;
        $this->word('badword', 'post');

        $this->actingAs($admin)
            ->putJson(route('admin.weekly-reviews.update', $review), [
                'revision' => 1,
                'title' => 'badword review',
                'excerpt' => 'Clean excerpt',
                'markdown_content' => 'Clean body',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title')
            ->assertJsonPath('moderation.fields.title.count', 1);

        $this->assertSame($originalTitle, $review->fresh()->title);
        $this->assertSame(1, $review->fresh()->revision);
    }

    public function test_weekly_review_checks_reference_titles_added_during_sync(): void
    {
        $admin = User::factory()->admin()->create();
        $review = WeeklyReview::factory()->create([
            'status' => 'REVIEW',
            'title' => 'Clean review',
            'excerpt' => 'Clean excerpt',
            'markdown_content' => 'Clean body',
        ]);
        $review->sourceArticles()->attach(
            AiArticle::factory()->create(['title' => 'A badword article']),
            ['citation_order' => 1],
        );
        $this->word('badword', 'post');

        $this->actingAs($admin)
            ->putJson(route('admin.weekly-reviews.update', $review), [
                'revision' => 1,
                'title' => 'Clean review',
                'excerpt' => 'Clean excerpt',
                'markdown_content' => 'Clean body with a citation [1].',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('markdown_content');

        $this->assertSame(1, $review->fresh()->revision);
        $this->assertSame('Clean body', $review->fresh()->markdown_content);
    }

    public function test_weekly_review_conversion_is_blocked_with_409_and_moderation(): void
    {
        $admin = User::factory()->admin()->create();
        $review = WeeklyReview::factory()->create([
            'status' => 'REVIEW',
            'title' => 'badword weekly',
            'excerpt' => 'Clean excerpt',
            'markdown_content' => 'Clean body',
        ]);
        $review->sourceArticles()->attach(AiArticle::factory()->create(), ['citation_order' => 1]);
        $this->word('badword', 'post');

        $this->actingAs($admin)
            ->postJson(route('admin.weekly-reviews.convert', $review))
            ->assertStatus(409)
            ->assertJsonValidationErrors('title')
            ->assertJsonPath('moderation.fields.title.count', 1);

        $this->assertDatabaseCount('posts', 0);
        $this->assertSame('REVIEW', $review->fresh()->status);
    }

    public function test_weekly_review_conversion_checks_the_generated_post_slug(): void
    {
        $admin = User::factory()->admin()->create();
        $review = WeeklyReview::factory()->create([
            'status' => 'REVIEW',
            'title' => 'Bad Word',
            'excerpt' => 'Clean excerpt',
            'markdown_content' => 'Clean body',
        ]);
        $review->sourceArticles()->attach(AiArticle::factory()->create(), ['citation_order' => 1]);
        $this->word('bad-word', 'post');

        $this->actingAs($admin)
            ->postJson(route('admin.weekly-reviews.convert', $review))
            ->assertStatus(409)
            ->assertJsonValidationErrors('slug')
            ->assertJsonPath('moderation.fields.slug.count', 1);

        $this->assertDatabaseCount('posts', 0);
        $this->assertSame('REVIEW', $review->fresh()->status);
    }

    public function test_clean_content_passes_through_existing_validation(): void
    {
        $admin = User::factory()->admin()->create();
        $this->word('badword', 'post');

        $this->actingAs($admin)
            ->post(route('admin.tags.store'), ['name' => 'Clean tag', 'slug' => 'clean-tag'])
            ->assertRedirect();

        $this->assertDatabaseHas('tags', ['name' => 'Clean tag']);
    }

    private function word(string $term, string $scope): ToxicWord
    {
        return ToxicWord::factory()->create([
            'term' => $term,
            'normalized_term' => mb_strtolower($term),
            'scopes' => [$scope],
            'match_mode' => ToxicWord::MATCH_WORD,
            'is_active' => true,
        ]);
    }
}
