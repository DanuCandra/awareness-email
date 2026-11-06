<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\Recipient;
use App\Models\TemplateCategory;
use App\Models\User;
use Database\Seeders\TemplateLibrarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function templateData(TemplateCategory $category): array
    {
        return ['name' => 'Informasi HR', 'subject' => 'Informasi kenaikan gaji 2027', 'category_id' => $category->id, 'delta' => ['ops' => [
            ['insert' => 'Halo ', 'attributes' => ['bold' => true]],
            ['insert' => "{{email}}\n"],
            ['insert' => "\n"],
            ['insert' => '{{tracking_', 'attributes' => ['italic' => true]],
            ['insert' => "link}}\n"],
        ]]];
    }

    private function csvPreview(string $contents): TestResponse
    {
        return $this->postJson(route('emails.import.preview'), ['file' => UploadedFile::fake()->createWithContent('emails.csv', $contents)]);
    }

    private function workbook(string $sheet, string $target = 'worksheets/sheet1.xml', string $shared = ''): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'awareness-');
        try {
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Email" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="'.htmlspecialchars($target, ENT_XML1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"/></Relationships>');
            $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
            if ($shared !== '') {
                $zip->addFromString('xl/sharedStrings.xml', $shared);
            }
            $zip->close();

            return UploadedFile::fake()->createWithContent('emails.xlsx', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    public function test_master_data_endpoints_require_an_authenticated_admin(): void
    {
        $this->getJson('/emails')->assertUnauthorized();
        $this->postJson('/emails', ['email' => 'private@example.com'])->assertUnauthorized();
        $this->deleteJson('/emails/bulk', ['ids' => [1]])->assertUnauthorized();
        $this->postJson('/emails/import/preview')->assertUnauthorized();
        $this->postJson('/emails/import/commit')->assertUnauthorized();
        $this->getJson('/email-templates')->assertUnauthorized();
        $this->postJson('/email-templates')->assertUnauthorized();
        $this->postJson('/template-categories')->assertUnauthorized();

        $this->assertDatabaseCount('recipients', 0);
        $this->assertDatabaseCount('email_templates', 0);
    }

    public function test_manual_email_is_normalized_and_the_client_cannot_set_its_source(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->postJson('/emails', ['email' => '  EMPLOYEE@EXAMPLE.COM ', 'source' => 'Excel', 'id' => 99]);

        $response->assertCreated()->assertJsonPath('data.email', 'employee@example.com')->assertJsonPath('data.source', 'Manual');
        $this->assertDatabaseHas('recipients', ['email' => 'employee@example.com', 'source' => 'Manual']);
        $this->getJson('/emails')->assertOk()->assertJsonCount(1, 'data')->assertJsonStructure(['data' => [['id', 'email', 'source', 'addedAt']]]);
    }

    public function test_duplicate_email_is_rejected_case_insensitively_without_overwriting(): void
    {
        $this->actingAs(User::factory()->create());
        Recipient::factory()->create(['email' => 'employee@example.com']);

        $this->postJson('/emails', ['email' => 'EMPLOYEE@EXAMPLE.COM'])
            ->assertUnprocessable()->assertJsonValidationErrors('email')->assertJsonPath('errors.email.0', 'Email ini sudah ada di daftar.');

        $this->assertDatabaseCount('recipients', 1);
    }

    public function test_email_can_be_edited_without_changing_its_import_source(): void
    {
        $this->actingAs(User::factory()->create());
        $recipient = Recipient::factory()->create(['email' => 'old@example.com', 'source' => 'Excel']);

        $this->putJson('/emails/'.$recipient->id, ['email' => 'NEW@EXAMPLE.COM', 'source' => 'Manual'])
            ->assertOk()->assertJsonPath('data.email', 'new@example.com')->assertJsonPath('data.source', 'Excel');

        $this->assertDatabaseHas('recipients', ['id' => $recipient->id, 'email' => 'new@example.com', 'source' => 'Excel']);
        $this->putJson('/emails/'.$recipient->id, ['email' => 'new@example.com'])->assertOk();
        $this->putJson('/emails/'.$recipient->id, ['email' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame('new@example.com', $recipient->fresh()->email);
    }

    public function test_single_and_bulk_deletion_remove_only_selected_emails(): void
    {
        $this->actingAs(User::factory()->create());
        $rows = Recipient::factory()->count(4)->create();

        $this->deleteJson('/emails/'.$rows[0]->id)->assertOk();
        $this->deleteJson('/emails/bulk', ['ids' => [$rows[1]->id, $rows[2]->id]])->assertOk()->assertExactJson(['deleted' => 2]);

        $this->assertDatabaseCount('recipients', 1);
        $this->assertModelExists($rows[3]);
        $this->deleteJson('/emails/bulk', ['ids' => []])->assertUnprocessable()->assertJsonValidationErrors('ids');
    }

    public function test_csv_preview_classifies_rows_without_writing_then_commit_is_idempotent(): void
    {
        $this->actingAs(User::factory()->create());
        Recipient::factory()->create(['email' => 'existing@example.com']);

        $response = $this->csvPreview("\xEF\xBB\xBFemail\r\n NEW@EXAMPLE.COM \r\nnew@example.com\r\nexisting@example.com\r\ninvalid\r\n\r\n");
        $response->assertOk()->assertJsonPath('valid', ['new@example.com'])->assertJsonPath('duplicates', 2)->assertJsonPath('invalid', 1)->assertJsonPath('blank', 1);
        $this->assertDatabaseCount('recipients', 1);
        $token = $response->json('token');

        $this->postJson('/emails/import/commit', ['token' => $token, 'emails' => ['injected@example.com']])->assertOk()->assertExactJson(['added' => 1, 'skipped' => 0]);
        $this->postJson('/emails/import/commit', ['token' => $token])->assertOk()->assertExactJson(['added' => 1, 'skipped' => 0]);

        $this->assertDatabaseCount('recipients', 2);
        $this->assertDatabaseHas('recipients', ['email' => 'new@example.com', 'source' => 'CSV']);
        $this->assertDatabaseMissing('recipients', ['email' => 'injected@example.com']);
    }

    public function test_email_added_after_preview_is_skipped_during_commit(): void
    {
        $this->actingAs(User::factory()->create());
        $token = $this->csvPreview("email\nnew@example.com\n")->assertOk()->json('token');
        Recipient::factory()->create(['email' => 'new@example.com', 'source' => 'Manual']);

        $this->postJson('/emails/import/commit', ['token' => $token])->assertOk()->assertExactJson(['added' => 0, 'skipped' => 1]);

        $this->assertDatabaseCount('recipients', 1);
        $this->assertDatabaseHas('recipients', ['email' => 'new@example.com', 'source' => 'Manual']);
    }

    public function test_the_ten_thousand_email_limit_can_be_previewed_and_committed(): void
    {
        $this->actingAs(User::factory()->create());
        $contents = "email\n";
        for ($index = 1; $index <= 10000; $index++) {
            $contents .= 'employee'.$index."@example.com\n";
        }

        $response = $this->csvPreview($contents);
        $response->assertOk()->assertJsonCount(10000, 'valid')->assertJsonPath('invalid', 0);
        $this->postJson('/emails/import/commit', ['token' => $response->json('token')])
            ->assertOk()->assertExactJson(['added' => 10000, 'skipped' => 0]);

        $this->assertDatabaseCount('recipients', 10000);
        $this->assertDatabaseHas('recipients', ['email' => 'employee10000@example.com', 'source' => 'CSV']);
    }

    public function test_preview_token_cannot_be_used_by_another_admin(): void
    {
        $this->actingAs(User::factory()->create());
        $token = $this->csvPreview("email\nnew@example.com\n")->assertOk()->json('token');

        $this->actingAs(User::factory()->create())->postJson('/emails/import/commit', ['token' => $token])
            ->assertUnprocessable()->assertJsonValidationErrors('token');

        $this->assertDatabaseCount('recipients', 0);
    }

    public function test_expired_preview_requires_the_file_to_be_checked_again(): void
    {
        $this->freezeTime();
        $this->actingAs(User::factory()->create());
        $token = $this->csvPreview("email\nnew@example.com\n")->assertOk()->json('token');
        $this->travel(16)->minutes();

        $this->postJson('/emails/import/commit', ['token' => $token])
            ->assertUnprocessable()->assertJsonPath('errors.token.0', 'Pratinjau impor kedaluwarsa atau tidak tersedia. Periksa kembali file Anda.');

        $this->assertDatabaseCount('recipients', 0);
    }

    /** @return array<string, array{string}> */
    public static function invalidCsvFiles(): array
    {
        return [
            'wrong header' => ["address\na@example.com"],
            'extra header' => ["email,name\na@example.com,A"],
            'empty' => [''],
            'header only' => ["email\n"],
            'unclosed quote' => ["email\n\"a@example.com"],
            'text after closing quote' => ["email\n\"a@example.com\"oops"],
            'too many rows' => ["email\n".str_repeat("a@example.com\n", 10001)],
        ];
    }

    #[DataProvider('invalidCsvFiles')]
    public function test_invalid_csv_is_rejected_before_any_email_is_saved(string $contents): void
    {
        $this->actingAs(User::factory()->create());

        $this->csvPreview($contents)->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('recipients', 0);
    }

    public function test_xlsx_reads_the_first_sheet_with_shared_and_inline_strings_and_rejects_formulas(): void
    {
        $this->actingAs(User::factory()->create());
        $file = $this->workbook('<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>
          <row r="1"><c r="A1" t="s"><v>0</v></c></row>
          <row r="2"><c r="A2" t="s"><v>1</v></c></row>
          <row r="3"><c r="A3" t="inlineStr"><is><t>INLINE@EXAMPLE.COM</t></is></c></row>
          <row r="4"><c r="A4" t="str"><f>"formula@example.com"</f><v>formula@example.com</v></c></row>
          <row r="5"><c r="A5" t="inlineStr"><is><t>extra@example.com</t></is></c><c r="B5" t="inlineStr"><is><t>Extra</t></is></c></row>
        </sheetData></worksheet>', shared: '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>email</t></si><si><t>SHARED@EXAMPLE.COM</t></si></sst>');

        $response = $this->postJson('/emails/import/preview', ['file' => $file]);
        $response->assertOk()->assertJsonPath('valid', ['shared@example.com', 'inline@example.com'])->assertJsonPath('invalid', 2);
        $this->postJson('/emails/import/commit', ['token' => $response->json('token')])->assertOk()->assertJsonPath('added', 2);

        $this->assertDatabaseCount('recipients', 2);
        $this->assertDatabaseHas('recipients', ['email' => 'shared@example.com', 'source' => 'Excel']);
        $this->assertDatabaseMissing('recipients', ['email' => 'formula@example.com']);
    }

    public function test_unsafe_or_damaged_workbooks_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $files = [
            UploadedFile::fake()->createWithContent('emails.xlsx', 'not a zip'),
            $this->workbook('<worksheet/>', 'https://example.com/external.xml'),
            $this->workbook('<!DOCTYPE worksheet [<!ENTITY name SYSTEM "file:///etc/passwd">]><worksheet>&name;</worksheet>'),
        ];

        foreach ($files as $file) {
            $this->postJson('/emails/import/preview', ['file' => $file])->assertUnprocessable()->assertJsonValidationErrors('file');
        }

        $this->assertDatabaseCount('recipients', 0);
    }

    public function test_unsupported_and_oversized_files_cannot_be_imported(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/emails/import/preview', ['file' => UploadedFile::fake()->createWithContent('emails.xls', "email\na@example.com")])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson('/emails/import/preview', ['file' => UploadedFile::fake()->create('emails.csv', 5121)])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('recipients', 0);
    }

    public function test_template_crud_preserves_rich_text_whitespace_and_link_tokens(): void
    {
        $this->actingAs(User::factory()->create());
        $category = TemplateCategory::factory()->create();
        $data = $this->templateData($category);

        $response = $this->postJson('/email-templates', [...$data, 'starter_key' => 'client-value']);
        $response->assertCreated()->assertJsonPath('data.category', $category->name)->assertJsonPath('data.delta', $data['delta']);
        $template = EmailTemplate::sole();
        $this->assertNull($template->starter_key);
        $this->assertSame($data['delta'], $template->delta);
        $this->getJson('/email-templates')->assertOk()->assertJsonCount(1, 'templates')->assertJsonCount(1, 'categories')->assertJsonPath('templates.0.id', (string) $template->id);

        $this->putJson('/email-templates/'.$template->id, [...$data, 'subject' => 'Subjek baru'])->assertOk()->assertJsonPath('data.subject', 'Subjek baru');
        $this->assertSame('Subjek baru', $template->fresh()->subject);
        $this->deleteJson('/email-templates/'.$template->id)->assertOk();
        $this->assertModelMissing($template);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidTemplateChanges(): array
    {
        return [
            'missing subject' => [['subject' => ''], 'subject'],
            'long name' => [['name' => str_repeat('a', 101)], 'name'],
            'long subject' => [['subject' => str_repeat('a', 181)], 'subject'],
            'missing category' => [['category_id' => 9999], 'category_id'],
            'no tracking token' => [['delta' => ['ops' => [['insert' => "Hello\n"]]]], 'body'],
            'oversized combined body' => [['delta' => ['ops' => [['insert' => str_repeat('a', 12000)], ['insert' => str_repeat('b', 12000).'{{tracking_link}}']]]], 'body'],
            'non-list operations' => [['delta' => ['ops' => ['arbitrary' => ['insert' => '{{tracking_link}}']]]], 'delta.ops'],
            'image embed' => [['delta' => ['ops' => [['insert' => ['image' => 'https://example.com/image.png']]]]], 'delta.ops.0.insert'],
            'executable link' => [['delta' => ['ops' => [['insert' => '{{tracking_link}}', 'attributes' => ['link' => 'javascript:alert(1)']]]]], 'delta.ops.0.attributes.link'],
            'unsupported formatting' => [['delta' => ['ops' => [['insert' => '{{tracking_link}}', 'attributes' => ['onclick' => 'alert(1)']]]]], 'delta.ops.0.attributes'],
        ];
    }

    #[DataProvider('invalidTemplateChanges')]
    public function test_invalid_template_is_rejected_without_saving(array $changes, string $field): void
    {
        $this->actingAs(User::factory()->create());
        $category = TemplateCategory::factory()->create();

        $this->postJson('/email-templates', array_replace($this->templateData($category), $changes))
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('email_templates', 0);
    }

    public function test_category_names_are_normalized_and_duplicates_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/template-categories', ['name' => '  HR   Payroll  '])->assertCreated()->assertJsonPath('data.name', 'HR Payroll');
        $this->postJson('/template-categories', ['name' => 'hr payroll'])->assertUnprocessable()->assertJsonValidationErrors('name_key');

        $this->assertDatabaseCount('template_categories', 1);
        $this->assertDatabaseHas('template_categories', ['name' => 'HR Payroll', 'name_key' => 'hr payroll']);
    }

    public function test_renaming_a_category_updates_template_labels_without_rewriting_the_message(): void
    {
        $this->actingAs(User::factory()->create());
        $category = TemplateCategory::factory()->create();
        $template = EmailTemplate::factory()->for($category, 'category')->create();
        $delta = $template->delta;

        $this->putJson('/template-categories/'.$category->id, ['name' => 'Benefit Perusahaan'])->assertOk()->assertJsonPath('data.name', 'Benefit Perusahaan');

        $this->assertSame($delta, $template->fresh()->delta);
        $this->getJson('/email-templates')->assertOk()->assertJsonPath('templates.0.category', 'Benefit Perusahaan');
    }

    public function test_deleting_a_used_category_requires_replacement_and_preserves_templates(): void
    {
        $this->actingAs(User::factory()->create());
        $category = TemplateCategory::factory()->create();
        $replacement = TemplateCategory::factory()->create();
        $template = EmailTemplate::factory()->for($category, 'category')->create();
        $delta = $template->delta;

        $this->deleteJson('/template-categories/'.$category->id)->assertUnprocessable()->assertJsonValidationErrors('replacement_id');
        $this->deleteJson('/template-categories/'.$category->id, ['replacement_id' => $category->id])->assertUnprocessable()->assertJsonValidationErrors('replacement_id');
        $this->assertModelExists($category);
        $this->deleteJson('/template-categories/'.$category->id, ['replacement_id' => $replacement->id])->assertOk();

        $this->assertModelMissing($category);
        $this->assertSame($replacement->id, $template->fresh()->template_category_id);
        $this->assertSame($delta, $template->fresh()->delta);
    }

    public function test_unused_category_can_be_deleted_without_a_replacement(): void
    {
        $this->actingAs(User::factory()->create());
        $category = TemplateCategory::factory()->create();

        $this->deleteJson('/template-categories/'.$category->id)->assertOk();

        $this->assertModelMissing($category);
    }

    public function test_initial_library_is_idempotent_and_keeps_user_edits(): void
    {
        $this->seed(TemplateLibrarySeeder::class);
        $this->assertDatabaseCount('email_templates', 3);
        $this->assertDatabaseCount('template_categories', 3);
        $template = EmailTemplate::firstOrFail();
        $template->update(['subject' => 'Subjek pilihan admin']);

        $this->seed(TemplateLibrarySeeder::class);

        $this->assertDatabaseCount('email_templates', 3);
        $this->assertSame('Subjek pilihan admin', $template->fresh()->subject);
        $this->assertDatabaseCount('recipients', 0);
    }
}
