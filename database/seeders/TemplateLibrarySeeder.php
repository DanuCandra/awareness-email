<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\TemplateCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TemplateLibrarySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            if (TemplateCategory::query()->exists() || EmailTemplate::query()->exists()) {
                return;
            }
            $samples = json_decode(file_get_contents(database_path('seeders/template-library.json')), true, 512, JSON_THROW_ON_ERROR);
            foreach ($samples as $sample) {
                $category = TemplateCategory::firstOrCreate(['name_key' => Str::lower($sample['category'])], ['name' => $sample['category']]);
                EmailTemplate::create(['name' => $sample['name'], 'subject' => $sample['subject'], 'delta' => $sample['delta'], 'template_category_id' => $category->id, 'starter_key' => $sample['id']]);
            }
        });
    }
}
