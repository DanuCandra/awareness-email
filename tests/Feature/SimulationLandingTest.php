<?php

namespace Tests\Feature;

use App\Models\Simulation;
use App\Models\SimulationRecipient;
use App\Models\WorkspaceSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_education_card_uses_saved_company_message_tips_and_contact_without_exposing_the_recipient(): void
    {
        $simulation = Simulation::factory()->create(['status' => 'running', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]);
        $recipient = SimulationRecipient::factory()->for($simulation)->create(['status' => 'sent']);
        WorkspaceSetting::factory()->create(['section' => 'identity', 'values' => [
            ...config('awareness.defaults.identity'), 'companyName' => 'PT Contoh & Rekan', 'contactEmail' => 'helpdesk@example.com',
        ]]);
        WorkspaceSetting::factory()->create(['section' => 'landing', 'values' => [
            ...config('awareness.defaults.landing'), 'title' => 'Pelajaran <penting>', 'message' => 'Pesan <script>alert(1)</script>',
            'tips' => "Periksa alamat pengirim.\n\nKonfirmasikan melalui jalur resmi.",
        ]]);

        $this->get(route('simulation-links.show', $recipient->token))->assertOk()
            ->assertSee('PT Contoh & Rekan')->assertSee('Pelajaran <penting>')->assertSee('Pesan <script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('Periksa alamat pengirim.')
            ->assertSee('Konfirmasikan melalui jalur resmi.')->assertSee('mailto:helpdesk@example.com', false)
            ->assertDontSee($recipient->email)->assertDontSee('<form', false)->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_confirmation_card_keeps_custom_message_and_does_not_show_education_or_empty_contact(): void
    {
        $simulation = Simulation::factory()->create(['status' => 'running', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]);
        $recipient = SimulationRecipient::factory()->for($simulation)->create(['status' => 'sent']);
        WorkspaceSetting::factory()->create(['section' => 'landing', 'values' => [
            ...config('awareness.defaults.landing'), 'type' => 'confirmation', 'title' => 'Terima kasih',
            'message' => 'Partisipasi Anda sangat berarti.', 'tips' => 'Tips yang tidak ditampilkan pada mode konfirmasi.',
        ]]);

        $this->get(route('simulation-links.show', $recipient->token))->assertOk()->assertSee('Terima kasih')
            ->assertSee('Partisipasi Anda sangat berarti.')->assertSee('Tautan berhasil dibuka.')
            ->assertDontSee('Kenali sebelum mengeklik')->assertDontSee('Tips yang tidak ditampilkan pada mode konfirmasi.')
            ->assertDontSee('mailto:', false)->assertDontSee($recipient->email);
    }
}
