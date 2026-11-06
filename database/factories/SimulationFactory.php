<?php

namespace Database\Factories;

use App\Models\Simulation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Simulation> */
class SimulationFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Simulasi HR', 'description' => '', 'status' => 'draft', 'dispatch_version' => (string) Str::uuid(),
            'sender_name' => 'HRD', 'sender_email' => 'hr@example.com', 'subject' => 'Informasi HR', 'delta' => ['ops' => [['insert' => "Halo {{email}}\n{{tracking_link}}\n"]]],
            'recipient_mode' => 'selected', 'starts_at' => null, 'ends_at' => null,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => ['status' => 'scheduled', 'starts_at' => now()->addHour(), 'ends_at' => now()->addDay()]);
    }
}
