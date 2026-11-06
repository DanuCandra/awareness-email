<?php

namespace Database\Factories;

use App\Models\Simulation;
use App\Models\SimulationRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SimulationRecipient> */
class SimulationRecipientFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['simulation_id' => Simulation::factory(), 'email' => fake()->unique()->safeEmail(), 'token' => Str::random(64), 'status' => 'pending'];
    }
}
