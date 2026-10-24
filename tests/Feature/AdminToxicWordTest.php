<?php

namespace Tests\Feature;

use App\Models\ToxicWord;
use App\Models\User;
use Database\Seeders\ToxicWordSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminToxicWordTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_toxic_words(): void
    {
        $this->get('/admin/toxic-words')->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_toxic_words(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/toxic-words')
            ->assertForbidden();
    }

    public function test_admin_can_view_the_dictionary(): void
    {
        $admin = User::factory()->admin()->create();
        ToxicWord::factory()->create(['term' => 'badword', 'normalized_term' => 'badword']);

        $this->actingAs($admin)
            ->get('/admin/toxic-words')
            ->assertOk()
            ->assertSeeText('Toxic Word Dictionary')
            ->assertSeeText('badword');
    }

    public function test_admin_can_create_a_toxic_word(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from('/admin/toxic-words')
            ->post('/admin/toxic-words', [
                'term' => '  Bad   Word  ',
                'scopes' => ['post', 'tag'],
                'match_mode' => ToxicWord::MATCH_WORD,
                'fold_diacritics' => '1',
                'is_active' => '1',
                'note' => 'Editorial note',
            ])
            ->assertRedirect('/admin/toxic-words')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('toxic_words', [
            'term' => 'Bad   Word',
            'normalized_term' => 'bad word',
            'match_mode' => ToxicWord::MATCH_WORD,
            'fold_diacritics' => true,
            'is_active' => true,
            'note' => 'Editorial note',
            'created_by' => $admin->id,
        ]);
    }

    public function test_duplicate_normalized_terms_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        ToxicWord::factory()->create(['term' => 'bad word', 'normalized_term' => 'bad word']);

        $this->actingAs($admin)
            ->from('/admin/toxic-words')
            ->post('/admin/toxic-words', [
                'term' => 'BAD   WORD',
                'scopes' => ['post'],
                'match_mode' => ToxicWord::MATCH_WORD,
            ])
            ->assertSessionHasErrors('term');
    }

    public function test_loose_mode_requires_at_least_four_characters(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from('/admin/toxic-words')
            ->post('/admin/toxic-words', [
                'term' => 'abc',
                'scopes' => ['post'],
                'match_mode' => ToxicWord::MATCH_LOOSE,
            ])
            ->assertSessionHasErrors('match_mode');
    }

    public function test_admin_can_update_a_toxic_word(): void
    {
        $admin = User::factory()->admin()->create();
        $word = ToxicWord::factory()->create(['term' => 'badword', 'normalized_term' => 'badword']);

        $this->actingAs($admin)
            ->from('/admin/toxic-words')
            ->put('/admin/toxic-words/'.$word->id, [
                'term' => 'Better Word',
                'scopes' => ['category'],
                'match_mode' => ToxicWord::MATCH_LOOSE,
                'is_active' => '0',
            ])
            ->assertRedirect('/admin/toxic-words')
            ->assertSessionHasNoErrors();

        $word->refresh();

        $this->assertSame('Better Word', $word->term);
        $this->assertSame('better word', $word->normalized_term);
        $this->assertSame(['category'], $word->scopes);
        $this->assertFalse($word->is_active);
        $this->assertSame($admin->id, $word->updated_by);
    }

    public function test_admin_can_toggle_and_delete_a_toxic_word(): void
    {
        $admin = User::factory()->admin()->create();
        $word = ToxicWord::factory()->create();

        $this->actingAs($admin)
            ->patch('/admin/toxic-words/'.$word->id.'/status', ['is_active' => '0'])
            ->assertRedirect();

        $this->assertFalse($word->fresh()->is_active);

        $this->actingAs($admin)
            ->delete('/admin/toxic-words/'.$word->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('toxic_words', ['id' => $word->id]);
    }

    public function test_json_index_supports_scope_and_status_filters(): void
    {
        $admin = User::factory()->admin()->create();
        ToxicWord::factory()->create(['term' => 'post-word', 'normalized_term' => 'post-word', 'scopes' => ['post']]);
        ToxicWord::factory()->create(['term' => 'comment-word', 'normalized_term' => 'comment-word', 'scopes' => ['comment']]);
        ToxicWord::factory()->inactive()->create(['term' => 'inactive-word', 'normalized_term' => 'inactive-word', 'scopes' => ['post']]);

        $this->actingAs($admin)
            ->getJson('/admin/toxic-words?scope=post&status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.term', 'post-word');
    }

    public function test_seeder_accepts_plain_string_entries(): void
    {
        config(['toxic_words.baseline' => ['Bad Word']]);

        $this->seed(ToxicWordSeeder::class);

        $this->assertDatabaseHas('toxic_words', [
            'term' => 'Bad Word',
            'normalized_term' => 'bad word',
            'match_mode' => ToxicWord::MATCH_WORD,
            'is_active' => true,
        ]);
    }

    public function test_default_baseline_seeds_the_curated_dictionary(): void
    {
        $baseline = config('toxic_words.baseline');

        $this->assertIsArray($baseline);
        $this->assertNotEmpty($baseline);

        $this->seed(ToxicWordSeeder::class);

        $this->assertSame(count($baseline), ToxicWord::query()->count());
        $this->assertDatabaseHas('toxic_words', [
            'term' => 'địt',
            'normalized_term' => 'địt',
            'match_mode' => ToxicWord::MATCH_WORD,
            'is_active' => true,
        ]);
    }
}
