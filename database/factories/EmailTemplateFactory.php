<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use App\Models\TemplateCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmailTemplate> */
class EmailTemplateFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['template_category_id' => TemplateCategory::factory(), 'name' => fake()->sentence(3), 'subject' => fake()->sentence(), 'delta' => ['ops' => [['insert' => "Informasi baru\n{{tracking_link}}\n"]]]];
    }
}
