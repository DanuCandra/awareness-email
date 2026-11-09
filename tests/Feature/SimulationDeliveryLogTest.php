<?php

namespace Tests\Feature;

use App\Jobs\ProcessSimulation;
use App\Mail\SimulationMessage;
use App\Models\Recipient;
use App\Models\Simulation;
use App\Models\SimulationDeliveryLog;
use App\Models\SimulationRecipient;
use App\Models\User;
use App\Models\WorkspaceSetting;
use App\SimulationDeliveryLogger;
use App\SimulationManager;
use App\SmtpDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class SimulationDeliveryLogTest extends TestCase
{
    use RefreshDatabase;

    private function dueSimulation(int $recipients = 1): Simulation
    {
        $simulation = Simulation::factory()->create(['status' => 'scheduled', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]);
        SimulationRecipient::factory()->count($recipients)->for($simulation)->create();

        return $simulation;
    }

    private function process(Simulation $simulation): void
    {
        (new ProcessSimulation($simulation->id, $simulation->dispatch_version))->withFakeQueueInteractions()
            ->handle(app(SimulationManager::class), app(SmtpDelivery::class));
    }

    public function test_log_page_and_data_require_admin_login(): void
    {
        $simulation = Simulation::factory()->create();

        $this->get(route('delivery-logs.index'))->assertRedirect(route('login'));
        $this->getJson(route('delivery-logs.index'))->assertUnauthorized();
        $this->getJson(route('delivery-logs.show', $simulation))->assertUnauthorized();
    }

    public function test_menu_empty_list_and_existing_history_show_no_invented_events(): void
    {
        $this->actingAs(User::factory()->create())->get(route('delivery-logs.index'))->assertOk()->assertSee('Log Pengiriman')->assertSee('Histori rinci dicatat sejak fitur ini aktif.');
        $this->getJson(route('delivery-logs.index'))->assertOk()->assertJsonPath('data', []);
        $simulation = $this->dueSimulation();

        $this->getJson(route('delivery-logs.show', $simulation))->assertOk()->assertJsonPath('data.events', [])
            ->assertJsonPath('data.simulation.target', 1)->assertJsonPath('data.simulation.waiting', 1);
    }

    public function test_cursors_deliver_bounded_ordered_pages_and_isolate_each_simulation(): void
    {
        $simulation = Simulation::factory()->create();
        $logs = SimulationDeliveryLog::factory()->for($simulation)->count(205)->create();
        $other = SimulationDeliveryLog::factory()->create(['message' => 'Other simulation secret']);
        $this->actingAs(User::factory()->create());

        $tail = $this->getJson(route('delivery-logs.show', $simulation));
        $tail->assertOk()->assertJsonCount(100, 'data.events')->assertJsonPath('data.hasMore', true)
            ->assertJsonPath('data.events.0.id', $logs[105]->id)->assertJsonPath('data.events.99.id', $logs[204]->id)
            ->assertDontSee($other->message)->assertDontSee('event_key')->assertDontSee('dispatch_version');
        $this->getJson(route('delivery-logs.show', [$simulation, 'before' => $logs[105]->id]))->assertOk()
            ->assertJsonCount(100, 'data.events')->assertJsonPath('data.events.0.id', $logs[5]->id)->assertJsonPath('data.hasMore', true);
        $this->getJson(route('delivery-logs.show', [$simulation, 'before' => $logs[5]->id]))->assertOk()
            ->assertJsonCount(5, 'data.events')->assertJsonPath('data.hasMore', false);
        $this->getJson(route('delivery-logs.show', [$simulation, 'after' => 0]))->assertOk()
            ->assertJsonCount(100, 'data.events')->assertJsonPath('data.events.0.id', $logs[0]->id)->assertJsonPath('data.hasMore', true);
        $this->getJson(route('delivery-logs.show', [$simulation, 'after' => $logs[204]->id]))->assertOk()
            ->assertJsonPath('data.events', [])->assertJsonPath('data.hasMore', false)->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_invalid_cursor_combinations_return_422_and_unknown_simulation_returns_404(): void
    {
        $simulation = Simulation::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->getJson(route('delivery-logs.show', [$simulation, 'after' => -1]))->assertUnprocessable()->assertJsonValidationErrors('after');
        $this->getJson(route('delivery-logs.show', [$simulation, 'after' => 1, 'before' => 10]))->assertUnprocessable()->assertJsonValidationErrors(['after', 'before']);
        $this->getJson(route('delivery-logs.show', '12345678-1234-4234-8234-123456789012'))->assertNotFound();
    }

    public function test_worker_records_live_lifecycle_and_finishes_delivery_once_before_evaluation_ends(): void
    {
        $this->freezeTime();
        WorkspaceSetting::factory()->smtp()->create();
        $simulation = $this->dueSimulation(2);
        Mail::fake();
        Queue::fake();

        $this->process($simulation);

        $this->assertSame(['running', 'sending', 'done', 'waiting'], $simulation->deliveryLogs()->orderBy('id')->pluck('event')->all());
        $this->assertDatabaseHas('simulation_delivery_logs', ['simulation_id' => $simulation->id, 'event' => 'done', 'email' => $simulation->recipients()->orderBy('id')->first()->email]);
        $this->travel(6)->seconds();
        $this->process($simulation->fresh());
        $this->travel(6)->seconds();
        $this->process($simulation->fresh());

        Mail::assertSent(SimulationMessage::class, 2);
        Queue::assertPushed(ProcessSimulation::class, 1);
        $this->assertSame(1, $simulation->deliveryLogs()->where('event', 'running')->count());
        $this->assertSame(1, $simulation->deliveryLogs()->where('event', 'delivery_completed')->count());
        $this->assertSame('running', $simulation->fresh()->status);
        $this->actingAs(User::factory()->create())->getJson(route('delivery-logs.show', $simulation))->assertOk()
            ->assertJsonPath('data.simulation.deliveryStatus', 'completed')->assertJsonPath('data.simulation.status', 'running')->assertJsonPath('data.simulation.sent', 2);
    }

    public function test_sending_log_is_visible_before_smtp_returns(): void
    {
        WorkspaceSetting::factory()->smtp()->create();
        $simulation = $this->dueSimulation();
        $this->actingAs(User::factory()->create());
        $this->mock(SmtpDelivery::class, function (MockInterface $mock) use ($simulation): void {
            $mock->shouldReceive('sendMessage')->once()->andReturnUsing(function () use ($simulation): void {
                $this->getJson(route('delivery-logs.show', $simulation))->assertOk()
                    ->assertJsonPath('data.simulation.processing', 1)->assertJsonPath('data.simulation.sent', 0)
                    ->assertJsonPath('data.events.1.event', 'sending');
            });
        });

        $this->process($simulation);

        $this->assertDatabaseHas('simulation_delivery_logs', ['simulation_id' => $simulation->id, 'event' => 'done']);
    }

    public function test_scheduling_is_logged_once_for_repeated_request_and_again_for_new_schedule(): void
    {
        WorkspaceSetting::factory()->smtp()->create();
        Recipient::factory()->create();
        $admin = User::factory()->create();
        Queue::fake();
        $data = ['clientToken' => (string) Str::uuid(), 'name' => 'Simulasi terjadwal', 'status' => 'scheduled', 'recipientMode' => 'all',
            'scheduledAt' => now()->addHour()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'endsAt' => now()->addDay()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'message' => ['subject' => 'Tes', 'delta' => ['ops' => [['insert' => "{{tracking_link}}\n"]]]],
        ];
        $manager = app(SimulationManager::class);

        $simulation = $manager->save($data, $admin->id);
        $manager->save($data, $admin->id);

        $this->assertSame(['queued'], $simulation->deliveryLogs()->pluck('event')->all());
        $manager->cancel($simulation);
        $manager->save($data, $admin->id, $simulation->fresh());
        $this->assertSame(['queued', 'cancelled', 'queued'], $simulation->deliveryLogs()->orderBy('id')->pluck('event')->all());
        Queue::assertPushed(ProcessSimulation::class, 2);
    }

    public function test_uncertain_smtp_attempt_and_worker_failure_never_expose_exception_secrets(): void
    {
        WorkspaceSetting::factory()->smtp()->create();
        $simulation = $this->dueSimulation();
        $this->mock(SmtpDelivery::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')->once()->andThrow(new TransportException('Password-secret-XYZ token-secret'));
        });

        $this->process($simulation);
        $this->travel(6)->seconds();
        $this->process($simulation->fresh());
        (new ProcessSimulation($simulation->id, $simulation->dispatch_version))->failed(new \RuntimeException('Password-secret-XYZ'));

        $this->assertSame(1, $simulation->deliveryLogs()->where('event', 'uncertain')->count());
        $this->assertSame(1, $simulation->deliveryLogs()->where('event', 'fail')->whereNotNull('email')->count());
        $this->assertDatabaseHas('simulation_delivery_logs', ['simulation_id' => $simulation->id, 'event' => 'fail']);
        $this->actingAs(User::factory()->create())->getJson(route('delivery-logs.show', $simulation))->assertOk()
            ->assertJsonPath('data.simulation.deliveryStatus', 'completed_with_issues')->assertJsonPath('data.simulation.uncertain', 1)
            ->assertDontSee('Password-secret-XYZ')->assertDontSee('token-secret');
    }

    public function test_repeated_waits_do_not_flood_logs_and_stale_job_versions_do_not_log(): void
    {
        $simulation = $this->dueSimulation();
        Mail::fake();

        $this->process($simulation);
        $this->process($simulation);
        (new ProcessSimulation($simulation->id, 'outdated-version'))->withFakeQueueInteractions()->handle(app(SimulationManager::class), app(SmtpDelivery::class));

        $this->assertSame(['waiting'], $simulation->deliveryLogs()->pluck('event')->all());
        Mail::assertNothingSent();
    }

    public function test_expiry_keeps_history_and_logs_skipped_and_stale_recipients_without_duplicates(): void
    {
        $simulation = $this->dueSimulation(2);
        $simulation->update(['ends_at' => now()->subSecond()]);
        $simulation->recipients()->orderBy('id')->first()->update(['status' => 'sending', 'attempted_at' => now()->subMinutes(6)]);

        app(SimulationManager::class)->expire($simulation);
        app(SimulationManager::class)->expire($simulation);

        $this->assertSame(['skipped', 'uncertain', 'evaluation_completed', 'delivery_completed'], $simulation->deliveryLogs()->orderBy('id')->pluck('event')->all());
        $recipient = $simulation->recipients()->where('status', 'uncertain')->sole();
        $email = $recipient->email;
        $recipient->delete();
        $this->assertDatabaseHas('simulation_delivery_logs', ['simulation_id' => $simulation->id, 'event' => 'uncertain', 'email' => $email, 'simulation_recipient_id' => null]);
    }

    public function test_scheduler_closes_stale_in_flight_process_after_evaluation_was_already_completed(): void
    {
        $this->freezeTime();
        $simulation = $this->dueSimulation();
        $simulation->update(['ends_at' => now()->subSecond()]);
        $simulation->recipients()->sole()->update(['status' => 'sending', 'attempted_at' => now()]);
        app(SimulationManager::class)->expire($simulation);
        $this->travel(6)->minutes();

        $this->artisan('simulations:dispatch')->assertSuccessful();

        $this->assertDatabaseHas('simulation_delivery_logs', ['simulation_id' => $simulation->id, 'event' => 'uncertain']);
        $this->assertDatabaseHas('simulation_delivery_logs', ['simulation_id' => $simulation->id, 'event' => 'delivery_completed']);
        $this->assertSame('uncertain', $simulation->recipients()->sole()->status);
    }

    public function test_cancelling_schedule_records_history_without_copying_history_to_duplicate(): void
    {
        $simulation = $this->dueSimulation();
        $logger = app(SimulationDeliveryLogger::class);
        $logger->record($simulation, 'queued', 'Terjadwal.', key: 'scheduled');
        $manager = app(SimulationManager::class);

        $manager->cancel($simulation);
        $copy = $manager->duplicate($simulation->fresh(), User::factory()->create()->id);

        $this->assertSame(['queued', 'cancelled'], $simulation->deliveryLogs()->orderBy('id')->pluck('event')->all());
        $this->assertSame(0, $copy->deliveryLogs()->count());
    }
}
