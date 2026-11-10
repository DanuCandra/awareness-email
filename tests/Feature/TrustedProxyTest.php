<?php

namespace Tests\Feature;

use App\Models\Simulation;
use App\Models\SimulationRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirect_uses_https_and_the_domain_forwarded_by_any_proxy(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25']);

        $response = $this->get('http://192.255.20.17/', [
            'X-Forwarded-Host' => 'payroll.ahost.my.id',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
        ]);

        $response->assertRedirect('https://payroll.ahost.my.id/login');
    }

    public function test_direct_ip_access_uses_http_for_the_login_redirect(): void
    {
        config(['app.url' => 'https://payroll.ahost.my.id']);

        $response = $this->get('http://192.255.20.17/');

        $response->assertRedirect('http://192.255.20.17/login');
    }

    public function test_simulation_click_records_the_client_ip_forwarded_by_the_proxy(): void
    {
        $simulation = Simulation::factory()->create([
            'status' => 'running', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(),
        ]);
        $recipient = SimulationRecipient::factory()->for($simulation)->create(['status' => 'sent']);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25']);

        $response = $this->get(route('simulation-links.show', $recipient->token), [
            'X-Forwarded-For' => '198.51.100.42',
            'X-Forwarded-Proto' => 'https',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('simulation_clicks', [
            'simulation_recipient_id' => $recipient->id,
            'ip' => '198.51.100.42',
        ]);
    }
}
