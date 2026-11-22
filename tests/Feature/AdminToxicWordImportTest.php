<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\ToxicWord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class AdminToxicWordImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('words.csv', $content);
    }

    public function test_non_admin_cannot_import_or_download_template(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('admin.toxic-words.import'), ['file' => $this->csv("term\nabc")])->assertForbidden();
        $this->actingAs($user)->get(route('admin.toxic-words.import-template'))->assertForbidden();
    }

    public function test_admin_can_import_csv_with_defaults_and_options(): void
    {
        $admin = User::factory()->admin()->create();
        $content = "term,scopes,match_mode,fold_diacritics,is_active,note\n"
            ."badword,\"post,comment\",WORD,0,1,ghi chú\n"
            ."another phrase,,LOOSE,yes,no,\n";

        $this->actingAs($admin)->post(route('admin.toxic-words.import'), ['file' => $this->csv($content)])
            ->assertRedirect()
            ->assertSessionHas('status', 'Đã import 2 từ mới, bỏ qua 0 từ đã có.');

        $first = ToxicWord::query()->where('term', 'badword')->firstOrFail();
        $this->assertSame(['post', 'comment'], $first->scopes);
        $this->assertTrue($first->is_active);
        $this->assertSame($admin->id, $first->created_by);

        $second = ToxicWord::query()->where('term', 'another phrase')->firstOrFail();
        $this->assertSame(config('toxic_words.scopes'), $second->scopes);
        $this->assertSame('LOOSE', $second->match_mode);
        $this->assertTrue($second->fold_diacritics);
        $this->assertFalse($second->is_active);
    }

    public function test_import_skips_existing_and_reports_invalid_rows(): void
    {
        $admin = User::factory()->admin()->create();
        ToxicWord::factory()->create(['term' => 'dupword', 'normalized_term' => 'dupword']);
        $content = "term,scopes,match_mode\nDupWord,,\nnew one,,\nx,,\nabc,bogus,\nabc,,LOOSE\nnew one,,\n";

        $response = $this->actingAs($admin)->post(route('admin.toxic-words.import'), ['file' => $this->csv($content)]);

        $response->assertSessionHas('status');
        $this->assertStringContainsString('Đã import 1 từ mới, bỏ qua 2 từ đã có, 3 dòng lỗi', session('status'));
        $this->assertCount(3, session('import_errors'));
        $this->assertDatabaseCount('toxic_words', 2);
    }

    public function test_import_requires_term_header_and_valid_file_type(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.toxic-words.import'), ['file' => $this->csv("word\nabc")])
            ->assertSessionHasErrors('file');
        $this->actingAs($admin)->post(route('admin.toxic-words.import'), ['file' => UploadedFile::fake()->create('words.pdf', 10)])
            ->assertSessionHasErrors('file');
        $this->actingAs($admin)->post(route('admin.toxic-words.import'), [])->assertSessionHasErrors('file');
    }

    public function test_admin_can_import_xlsx_and_imported_words_are_enforced(): void
    {
        $admin = User::factory()->admin()->create();
        $path = tempnam(sys_get_temp_dir(), 'tw').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['term', 'scopes']));
        $writer->addRow(Row::fromValues(['zzbadzz', 'comment']));
        $writer->close();

        $this->actingAs($admin)
            ->post(route('admin.toxic-words.import'), ['file' => new UploadedFile($path, 'words.xlsx', null, null, true)])
            ->assertSessionHas('status', 'Đã import 1 từ mới, bỏ qua 0 từ đã có.');

        $post = Post::factory()->published()->create();
        $this->actingAs(User::factory()->create())
            ->postJson(route('articles.comments.store', $post->slug), ['content' => 'this is zzbadzz text'])
            ->assertUnprocessable();
    }

    public function test_admin_can_download_template(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.toxic-words.import-template'))
            ->assertOk()
            ->assertDownload('toxic-words-template.xlsx');
    }

    public function test_dictionary_page_shows_import_form(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.toxic-words.index'))
            ->assertOk()
            ->assertSee(route('admin.toxic-words.import'), false);
    }

    public function test_template_rows_are_comments_and_import_nothing(): void
    {
        $admin = User::factory()->admin()->create();
        $path = tempnam(sys_get_temp_dir(), 'tw').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['term', 'scopes']));
        $writer->addRow(Row::fromValues(['# ví dụ: từ cần chặn', 'post']));
        $writer->close();

        $this->actingAs($admin)
            ->post(route('admin.toxic-words.import'), ['file' => new UploadedFile($path, 'words.xlsx', null, null, true)])
            ->assertSessionHas('status', 'Đã import 0 từ mới, bỏ qua 0 từ đã có.');
        $this->assertDatabaseCount('toxic_words', 0);
    }

    public function test_xlsx_boolean_false_is_preserved_and_zero_note_is_kept(): void
    {
        $admin = User::factory()->admin()->create();
        $path = tempnam(sys_get_temp_dir(), 'tw').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['term', 'is_active', 'note']));
        $writer->addRow(Row::fromValues(['disabledword', false, '0']));
        $writer->close();

        $this->actingAs($admin)->post(route('admin.toxic-words.import'), ['file' => new UploadedFile($path, 'words.xlsx', null, null, true)]);

        $word = ToxicWord::query()->where('term', 'disabledword')->firstOrFail();
        $this->assertFalse($word->is_active);
        $this->assertSame('0', $word->note);
    }

    public function test_semicolon_and_tab_delimited_csv_are_supported(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.toxic-words.import'), ['file' => $this->csv('term;scopes
semiword;comment
')]);
        $this->actingAs($admin)->post(route('admin.toxic-words.import'), ['file' => $this->csv("term\tscopes\ntabword\tpost\n")]);

        $this->assertSame(['comment'], ToxicWord::query()->where('term', 'semiword')->firstOrFail()->scopes);
        $this->assertSame(['post'], ToxicWord::query()->where('term', 'tabword')->firstOrFail()->scopes);
    }

    public function test_empty_file_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.toxic-words.import'), ['file' => $this->csv('')])
            ->assertSessionHasErrors('file');
    }

    public function test_surrounding_unicode_whitespace_cannot_bypass_minimum_length(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.toxic-words.import'), ['file' => $this->csv("term\n\u{00A0}x\u{00A0}\n")]);

        $this->assertDatabaseCount('toxic_words', 0);
        $this->assertCount(1, session('import_errors'));
    }

    public function test_overlong_normalized_terms_are_reported_without_aborting_valid_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $expanding = str_repeat("\u{0130}", 70);

        $this->actingAs($admin)->post(route('admin.toxic-words.import'), ['file' => $this->csv("term\n{$expanding}\ngoodword\n")]);

        $this->assertDatabaseHas('toxic_words', ['term' => 'goodword']);
        $this->assertCount(1, session('import_errors'));
    }

    public function test_capacity_counts_only_active_words_and_continues_reporting_after_limit(): void
    {
        config(['toxic_words.max_terms' => 2]);
        $admin = User::factory()->admin()->create();
        ToxicWord::factory()->create(['term' => 'old inactive', 'normalized_term' => 'old inactive', 'is_active' => false]);
        ToxicWord::factory()->create(['term' => 'old active', 'normalized_term' => 'old active', 'is_active' => true]);

        $content = "term,is_active\nnew active,1\nanother active,1\nstill inactive,0\nold active,1\nx,1\n";
        $this->actingAs($admin)->post(route('admin.toxic-words.import'), ['file' => $this->csv($content)]);

        $this->assertDatabaseHas('toxic_words', ['term' => 'new active']);
        $this->assertDatabaseHas('toxic_words', ['term' => 'still inactive']);
        $this->assertDatabaseMissing('toxic_words', ['term' => 'another active']);
        $this->assertStringContainsString('Đã import 2 từ mới, bỏ qua 1 từ đã có, 2 dòng lỗi', session('status'));
    }

    public function test_import_bumps_cache_version_so_new_words_apply_immediately(): void
    {
        $admin = User::factory()->admin()->create();
        $before = ToxicWord::cacheVersion();

        $this->actingAs($admin)->post(route('admin.toxic-words.import'), ['file' => $this->csv("term\nversionword\n")]);

        $this->assertGreaterThan($before, ToxicWord::cacheVersion());
    }
}
