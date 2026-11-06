<?php

namespace Database\Factories;

use App\Models\TemplateCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TemplateCategory> */
class TemplateCategoryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => $name = fake()->unique()->word(), 'name_key' => strtolower($name)];
    }
}
