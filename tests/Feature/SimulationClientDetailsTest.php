<?php

namespace Tests\Feature;

use App\Models\Simulation;
use App\Models\SimulationClick;
use App\Models\SimulationRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationClientDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_browser_comes_from_the_clicked_link_and_not_the_admin_browser(): void
    {
        $simulation = Simulation::factory()->create(['status' => 'running', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]);
        $recipient = SimulationRecipient::factory()->for($simulation)->create(['status' => 'sent', 'sent_at' => now()]);
        $agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36 Edg/130.0';

        $this->withHeaders(['User-Agent' => $agent])->get(route('simulation-links.show', $recipient->token))->assertOk();
        $response = $this->actingAs(User::factory()->create())->withHeaders(['User-Agent' => 'Admin browser'])->getJson(route('campaigns.data', $simulation));

        $response->assertOk()->assertJsonPath('data.recipientRows.0.browser', 'Edge')
            ->assertJsonPath('data.recipientRows.0.os', 'Windows')->assertJsonPath('data.recipientRows.0.device', 'Desktop')
            ->assertJsonPath('data.recipientRows.0.events.0.browser', 'Edge')->assertDontSee($agent);
        $this->assertDatabaseHas('simulation_clicks', ['simulation_recipient_id' => $recipient->id, 'user_agent' => $agent]);
    }

    public function test_historical_events_keep_each_client_and_latest_client_matches_latest_ip(): void
    {
        $simulation = Simulation::factory()->create();
        $recipient = SimulationRecipient::factory()->for($simulation)->create(['status' => 'sent']);
        $old = SimulationClick::factory()->for($recipient, 'recipient')->create([
            'clicked_at' => now()->subHour(), 'ip' => '10.0.0.1',
            'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0',
        ]);
        SimulationClick::factory()->for($recipient, 'recipient')->create([
            'clicked_at' => now(), 'ip' => '10.0.0.2',
            'user_agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36',
        ]);

        $this->actingAs(User::factory()->create())->getJson(route('campaigns.index', ['recipients' => 1]))->assertOk()
            ->assertJsonPath('data.0.recipientRows.0.browser', 'Chrome')->assertJsonPath('data.0.recipientRows.0.os', 'Android')
            ->assertJsonPath('data.0.recipientRows.0.device', 'Ponsel')->assertJsonPath('data.0.recipientRows.0.ip', '10.0.0.2')
            ->assertJsonPath('data.0.recipientRows.0.events.0.browser', 'Firefox')->assertJsonPath('data.0.recipientRows.0.events.0.os', 'Linux')
            ->assertJsonPath('data.0.recipientRows.0.events.1.browser', 'Chrome');
        $this->assertSame('Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0', $old->fresh()->user_agent);
    }

    public function test_missing_or_unrecognized_client_is_unknown_and_unclicked_recipient_has_no_client(): void
    {
        $simulation = Simulation::factory()->create();
        $clicked = SimulationRecipient::factory()->for($simulation)->create(['status' => 'sent']);
        SimulationClick::factory()->for($clicked, 'recipient')->create(['user_agent' => 'Custom client without platform']);
        $unclicked = SimulationRecipient::factory()->for($simulation)->create(['status' => 'sent']);

        $response = $this->actingAs(User::factory()->create())->getJson(route('campaigns.data', $simulation))->assertOk();
        $rows = collect($response->json('data.recipientRows'))->keyBy('recipientId');

        $this->assertSame('Tidak diketahui', $rows[$clicked->id]['browser']);
        $this->assertSame('Tidak diketahui', $rows[$clicked->id]['os']);
        $this->assertSame('Tidak diketahui', $rows[$clicked->id]['device']);
        $this->assertSame('', $rows[$unclicked->id]['browser']);
        $this->assertSame('', $rows[$unclicked->id]['os']);
        $this->assertSame('', $rows[$unclicked->id]['device']);
    }
}
