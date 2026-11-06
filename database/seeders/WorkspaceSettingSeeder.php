<?php

namespace Database\Seeders;

use App\Models\WorkspaceSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WorkspaceSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('awareness.defaults') as $section => $values) {
            WorkspaceSetting::firstOrCreate(['section' => $section], ['values' => $values, 'revision' => (string) Str::uuid()]);
        }
    }
}
