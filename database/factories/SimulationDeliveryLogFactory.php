<?php

namespace Database\Factories;

use App\Models\Simulation;
use App\Models\SimulationDeliveryLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SimulationDeliveryLog> */
class SimulationDeliveryLogFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['simulation_id' => Simulation::factory(), 'event' => 'running', 'message' => 'Proses pengiriman dimulai.', 'occurred_at' => now()];
    }
}
