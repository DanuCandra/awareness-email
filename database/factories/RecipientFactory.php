<?php

namespace Database\Factories;

use App\Models\Recipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Recipient> */
class RecipientFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['email' => strtolower(fake()->unique()->safeEmail()), 'source' => 'Manual'];
    }
}
