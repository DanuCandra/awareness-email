<?php

namespace Tests\Feature;

use App\Models\Simulation;
use App\Models\SimulationClick;
use App\Models\SimulationRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_data_requires_login(): void
    {
        $this->getJson(route('dashboard.data'))->assertUnauthorized();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_empty_dashboard_returns_no_sample_data(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('dashboard.data'))->assertOk()
            ->assertJsonPath('data.simulations', [])->assertJsonPath('data.selected', null)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Data contoh')->assertSee('dashboardProgress');
    }

    public function test_summary_matches_simulation_counts_and_only_first_within_period_clicks(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 9)->setTime(8, 0));
        $simulation = Simulation::factory()->create(['status' => 'running', 'starts_at' => '2026-11-08 23:00:00', 'ends_at' => '2026-11-09 23:00:00']);
        $recipients = SimulationRecipient::factory()->for($simulation)->count(6)->sequence(
            ['status' => 'sent', 'email' => 'clicked@example.com'], ['status' => 'sent'], ['status' => 'pending'], ['status' => 'uncertain'], ['status' => 'skipped'], ['status' => 'sending'],
        )->create();
        SimulationClick::factory()->for($recipients[0], 'recipient')->create(['clicked_at' => '2026-11-09 00:05:00', 'ip' => '10.1.1.10']);
        SimulationClick::factory()->for($recipients[0], 'recipient')->create(['clicked_at' => '2026-11-09 01:05:00', 'ip' => '10.1.1.11']);
        SimulationClick::factory()->for($recipients[0], 'recipient')->create(['period' => 'before', 'clicked_at' => '2026-11-08 22:00:00']);
        SimulationClick::factory()->for($recipients[1], 'recipient')->create(['period' => 'after', 'clicked_at' => '2026-11-10 00:00:00']);
        SimulationClick::factory()->for($recipients[3], 'recipient')->create();
        $other = Simulation::factory()->create();
        SimulationClick::factory()->for(SimulationRecipient::factory()->for($other)->create(['status' => 'sent']), 'recipient')->create();

        $response = $this->actingAs(User::factory()->create())->getJson(route('dashboard.data', ['simulation' => $simulation->id]));

        $response->assertOk()->assertJsonPath('data.selected.target', 6)->assertJsonPath('data.selected.sent', 2)
            ->assertJsonPath('data.selected.clicked', 1)->assertJsonPath('data.selected.unclicked', 1)->assertJsonPath('data.selected.rate', 50)
            ->assertJsonPath('data.selected.pending', 2)->assertJsonPath('data.selected.uncertain', 1)->assertJsonPath('data.selected.skipped', 1)
            ->assertJsonPath('data.selected.progress', 66.7)
            ->assertJsonPath('data.selected.activity', ['labels' => ['09 Nov 07.00'], 'counts' => [1]])
            ->assertJsonCount(1, 'data.selected.recent')->assertJsonPath('data.selected.recent.0.email', 'clicked@example.com')
            ->assertJsonPath('data.selected.recent.0.ip', '10.1.1.10')->assertDontSee($recipients[0]->token);
        $this->getJson(route('campaigns.data', $simulation))->assertOk()
            ->assertJsonPath('data.clicked', $response->json('data.selected.clicked'))->assertJsonPath('data.sent', $response->json('data.selected.sent'));
    }

    public function test_expired_simulation_reports_completed_and_pending_as_skipped_without_scheduler(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 9)->setTime(8, 0));
        $simulation = Simulation::factory()->create(['status' => 'running', 'starts_at' => now()->subDay(), 'ends_at' => now()->subMinute()]);
        SimulationRecipient::factory()->for($simulation)->create();

        $this->actingAs(User::factory()->create())->getJson(route('dashboard.data'))->assertOk()
            ->assertJsonPath('data.selected.id', $simulation->id)->assertJsonPath('data.selected.status', 'completed')
            ->assertJsonPath('data.selected.pending', 0)->assertJsonPath('data.selected.skipped', 1)->assertJsonPath('data.selected.progress', 100);
        $this->assertSame('running', $simulation->fresh()->status);
    }

    public function test_latest_simulation_is_selected_and_zero_delivery_has_zero_rate(): void
    {
        Simulation::factory()->create(['created_at' => now()->subDay()]);
        $latest = Simulation::factory()->create();
        SimulationRecipient::factory()->for($latest)->count(2)->create();

        $this->actingAs(User::factory()->create())->getJson(route('dashboard.data'))->assertOk()
            ->assertJsonPath('data.selected.id', $latest->id)->assertJsonPath('data.selected.rate', 0)
            ->assertJsonPath('data.selected.activity', ['labels' => [], 'counts' => []])->assertJsonPath('data.selected.recent', []);
    }

    public function test_recent_clicks_are_limited_ordered_and_use_wib_across_dates(): void
    {
        $simulation = Simulation::factory()->scheduled()->create();
        foreach (range(1, 8) as $day) {
            $recipient = SimulationRecipient::factory()->for($simulation)->create(['status' => 'sent', 'email' => 'day'.$day.'@example.com']);
            SimulationClick::factory()->for($recipient, 'recipient')->create(['clicked_at' => '2026-11-'.sprintf('%02d', $day).' 20:05:00']);
        }

        $this->actingAs(User::factory()->create())->getJson(route('dashboard.data'))->assertOk()
            ->assertJsonCount(6, 'data.selected.recent')->assertJsonPath('data.selected.recent.0.email', 'day8@example.com')
            ->assertJsonPath('data.selected.recent.5.email', 'day3@example.com')
            ->assertJsonPath('data.selected.activity.labels.0', '02 Nov 03.00')->assertJsonPath('data.selected.activity.labels.7', '09 Nov 03.00');
    }

    public function test_invalid_selection_returns_validation_error_and_missing_selection_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('dashboard.data', ['simulation' => 'invalid']))
            ->assertUnprocessable()->assertJsonPath('errors.simulation.0', 'Pilihan Simulasi tidak valid.');
        $this->getJson(route('dashboard.data', ['simulation' => '12345678-1234-4234-8234-123456789012']))->assertNotFound();
    }
}
