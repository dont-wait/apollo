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
}
