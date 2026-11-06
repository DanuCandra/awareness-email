<?php

namespace Database\Factories;

use App\Models\SimulationClick;
use App\Models\SimulationRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SimulationClick> */
class SimulationClickFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['simulation_recipient_id' => SimulationRecipient::factory(), 'ip' => '10.0.0.10', 'user_agent' => 'Test browser', 'clicked_at' => now(), 'period' => 'within'];
    }
}
