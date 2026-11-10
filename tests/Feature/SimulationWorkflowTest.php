<?php

namespace Tests\Feature;

use App\Jobs\ProcessSimulation;
use App\Mail\SimulationMessage;
use App\Models\Recipient;
use App\Models\Simulation;
use App\Models\SimulationRecipient;
use App\Models\User;
use App\Models\WorkspaceSetting;
use App\SimulationManager;
use App\SmtpDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class SimulationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string,mixed> */
    private function data(string $status = 'draft'): array
    {
        return ['clientToken' => (string) Str::uuid(), 'name' => 'Simulasi payroll', 'description' => 'Evaluasi internal', 'status' => $status, 'senderName' => 'HRD Khusus',
            'senderEmail' => 'spoofed@example.com', 'recipientMode' => 'all', 'recipientIds' => [],
            'scheduledAt' => $status === 'scheduled' ? now()->addHour()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i') : '',
            'endsAt' => $status === 'scheduled' ? now()->addDay()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i') : '',
            'message' => ['subject' => 'Informasi payroll', 'delta' => ['ops' => [['insert' => 'Halo ', 'attributes' => ['bold' => true]], ['insert' => "{{email}}\n"], ['insert' => "\n"], ['insert' => '{{tracking_'], ['insert' => "link}}\n"]]]],
        ];
    }

    private function runJob(Simulation $simulation): ProcessSimulation
    {
        $job = (new ProcessSimulation($simulation->id, $simulation->dispatch_version))->withFakeQueueInteractions();
        $job->handle(app(SimulationManager::class), app(SmtpDelivery::class));

        return $job;
    }

    private function dueSimulation(int $recipients = 1): Simulation
    {
        $simulation = Simulation::factory()->create(['status' => 'scheduled', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]);
        SimulationRecipient::factory()->count($recipients)->for($simulation)->create();

        return $simulation;
    }

    public function test_simulation_endpoints_require_login(): void
    {
        $simulation = Simulation::factory()->create();
        $this->getJson('/simulasi')->assertUnauthorized();
        $this->getJson('/simulasi/'.$simulation->id)->assertUnauthorized();
        $this->postJson('/simulasi', $this->data())->assertUnauthorized();
        $this->putJson('/simulasi/'.$simulation->id, $this->data())->assertUnauthorized();
        $this->postJson('/simulasi/'.$simulation->id.'/duplicate')->assertUnauthorized();
        $this->postJson('/simulasi/'.$simulation->id.'/cancel')->assertUnauthorized();
        $this->deleteJson('/simulasi/'.$simulation->id)->assertUnauthorized();
    }

    public function test_draft_preserves_rich_text_and_actual_email_snapshots_without_smtp(): void
    {
        $this->actingAs(User::factory()->create());
        $recipient = Recipient::factory()->create(['email' => 'employee@example.com']);
        $data = $this->data();
        $response = $this->postJson('/simulasi', $data);
        $response->assertSuccessful()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.target', 1)->assertJsonPath('data.message.delta', $data['message']['delta']);
        $simulation = Simulation::sole();
        $this->assertSame($data['message']['delta'], $simulation->delta);
        $this->assertDatabaseHas('simulation_recipients', ['simulation_id' => $simulation->id, 'recipient_id' => $recipient->id, 'email' => 'employee@example.com', 'status' => 'pending']);
        $recipient->update(['email' => 'changed@example.com']);
        $this->assertSame('employee@example.com', $simulation->recipients()->sole()->email);
        $this->getJson('/simulasi/'.$simulation->id)->assertOk()->assertJsonPath('data.recipientRows.0.email', 'employee@example.com')->assertJsonMissingPath('data.recipientRows.0.token');
    }

    public function test_scheduling_uses_saved_smtp_and_queues_after_commit_for_wib_start(): void
    {
        $this->travelTo('2026-11-06 06:00:00');
        $this->actingAs(User::factory()->create());
        WorkspaceSetting::factory()->smtp()->create();
        Recipient::factory()->count(2)->create();
        Queue::fake([ProcessSimulation::class]);
        $response = $this->postJson('/simulasi', $this->data('scheduled'));
        $response->assertSuccessful()->assertJsonPath('data.senderEmail', 'awareness@example.com')->assertJsonPath('data.senderName', 'HRD Khusus')->assertJsonPath('data.target', 2)->assertJsonPath('data.sendIntervalSeconds', 5);
        $simulation = Simulation::sole();
        $this->assertSame('2026-11-06 07:00:00', $simulation->starts_at->format('Y-m-d H:i:s'));
        Queue::assertPushed(ProcessSimulation::class, fn (ProcessSimulation $job): bool => $job->simulationId === $simulation->id && $job->queue === 'simulations' && $job->connection === 'database' && $job->afterCommit === true && $job->delay->equalTo($simulation->starts_at));
    }

    public function test_scheduled_creation_is_idempotent_for_a_retried_submit(): void
    {
        $this->actingAs(User::factory()->create());
        WorkspaceSetting::factory()->smtp()->create();
        Recipient::factory()->create();
        Queue::fake([ProcessSimulation::class]);
        $data = $this->data('scheduled');
        $id = $this->postJson('/simulasi', $data)->assertSuccessful()->json('data.id');
        $this->postJson('/simulasi', $data)->assertSuccessful()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('simulations', 1);
        $this->assertDatabaseCount('simulation_recipients', 1);
        Queue::assertPushed(ProcessSimulation::class, 1);
    }

    public function test_selected_recipients_are_snapshotted_and_master_deletion_keeps_the_audit(): void
    {
        $this->actingAs(User::factory()->create());
        $rows = Recipient::factory()->count(2)->create();
        $this->postJson('/simulasi', [...$this->data(), 'recipientMode' => 'selected', 'recipientIds' => [$rows[1]->id]])->assertSuccessful()->assertJsonPath('data.target', 1);
        $rows[1]->delete();
        $snapshot = SimulationRecipient::sole();
        $this->assertSame($rows[1]->email, $snapshot->email);
        $this->assertNull($snapshot->recipient_id);
    }

    /** @return array<string,array{array<string,mixed>,string}> */
    public static function invalidSchedules(): array
    {
        return [
            'past start' => [['scheduledAt' => '2000-01-01T09:00'], 'scheduledAt'],
            'reversed end' => [['endsAt' => '2000-01-01T09:00'], 'endsAt'],
            'bad date' => [['scheduledAt' => '2026-02-30T09:00'], 'scheduledAt'],
            'forged status' => [['status' => 'completed'], 'status'],
            'unknown recipient' => [['recipientMode' => 'selected', 'recipientIds' => [999999]], 'recipientIds.0'],
            'empty selection' => [['recipientMode' => 'selected', 'recipientIds' => []], 'recipients'],
            'no link' => [['message' => ['subject' => 'Subject', 'delta' => ['ops' => [['insert' => 'No link']]]]], 'body'],
            'executable formatting' => [['message' => ['subject' => 'Subject', 'delta' => ['ops' => [['insert' => '{{tracking_link}}', 'attributes' => ['link' => 'javascript:alert(1)']]]]]], 'message.delta.ops.0.attributes.link'],
        ];
    }

    #[DataProvider('invalidSchedules')]
    public function test_invalid_simulation_is_not_saved_or_queued(array $changes, string $field): void
    {
        $this->actingAs(User::factory()->create());
        WorkspaceSetting::factory()->smtp()->create();
        Recipient::factory()->create();
        Queue::fake([ProcessSimulation::class]);
        $this->postJson('/simulasi', array_replace($this->data('scheduled'), $changes))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('simulations', 0);
        Queue::assertNothingPushed();
    }

    public function test_scheduling_requires_smtp_and_nonempty_recipients(): void
    {
        $this->actingAs(User::factory()->create());
        Recipient::factory()->create();
        Queue::fake([ProcessSimulation::class]);
        $this->postJson('/simulasi', $this->data('scheduled'))->assertUnprocessable()->assertJsonValidationErrors('senderEmail');
        Recipient::query()->delete();
        WorkspaceSetting::factory()->smtp()->create();
        $this->postJson('/simulasi', $this->data('scheduled'))->assertUnprocessable()->assertJsonValidationErrors('recipients');
        $this->assertDatabaseCount('simulations', 0);
        Queue::assertNothingPushed();
    }

    public function test_only_drafts_can_be_edited_or_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $simulation = Simulation::factory()->scheduled()->create();
        Queue::fake([ProcessSimulation::class]);
        $this->putJson('/simulasi/'.$simulation->id, $this->data())->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->deleteJson('/simulasi/'.$simulation->id)->assertUnprocessable()->assertJsonValidationErrors('status');
        $simulation->update(['status' => 'draft']);
        $this->putJson('/simulasi/'.$simulation->id, [...$this->data(), 'name' => 'Draft edited'])->assertOk()->assertJsonPath('data.name', 'Draft edited');
        $this->deleteJson('/simulasi/'.$simulation->id)->assertOk();
        $this->assertModelMissing($simulation);
        Queue::assertNothingPushed();
    }

    public function test_cancel_invalidates_old_jobs_even_when_the_same_simulation_is_rescheduled(): void
    {
        $this->actingAs(User::factory()->create());
        $simulation = $this->dueSimulation();
        $old = new ProcessSimulation($simulation->id, $simulation->dispatch_version);
        Mail::fake();
        $this->postJson('/simulasi/'.$simulation->id.'/cancel')->assertOk()->assertJsonPath('data.status', 'draft');
        $simulation->refresh()->update(['status' => 'scheduled', 'starts_at' => now()->subMinute()]);
        $old->handle(app(SimulationManager::class), app(SmtpDelivery::class));
        Mail::assertNothingOutgoing();
        $this->assertSame('pending', $simulation->recipients()->sole()->status);
    }

    public function test_running_simulations_cannot_have_their_schedule_cancelled(): void
    {
        $this->actingAs(User::factory()->create());
        $simulation = Simulation::factory()->create(['status' => 'running']);
        $this->postJson('/simulasi/'.$simulation->id.'/cancel')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame('running', $simulation->fresh()->status);
    }

    public function test_duplicate_clears_dates_and_delivery_evidence_without_modifying_the_original(): void
    {
        $this->actingAs(User::factory()->create());
        $recipient = Recipient::factory()->create();
        $simulation = Simulation::factory()->scheduled()->create(['recipient_mode' => 'selected']);
        $snapshot = SimulationRecipient::factory()->for($simulation)->create(['recipient_id' => $recipient->id, 'email' => $recipient->email, 'status' => 'sent', 'sent_at' => now()]);
        $snapshot->clicks()->create(['ip' => '10.0.0.2', 'clicked_at' => now(), 'period' => 'within']);
        $response = $this->postJson('/simulasi/'.$simulation->id.'/duplicate');
        $response->assertSuccessful()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.scheduledAt', '')->assertJsonPath('data.sent', 0)->assertJsonPath('data.clicked', 0);
        $this->assertSame('sent', $snapshot->fresh()->status);
        $this->assertDatabaseCount('simulation_clicks', 1);
        $this->assertDatabaseCount('simulations', 2);
    }

    public function test_job_sends_exactly_one_recipient_with_saved_from_and_a_personal_tracking_url(): void
    {
        WorkspaceSetting::factory()->smtp()->create();
        $simulation = $this->dueSimulation(2);
        Queue::fake([ProcessSimulation::class]);
        Mail::fake();
        $this->runJob($simulation);
        $this->assertSame(1, $simulation->recipients()->where('status', 'sent')->count());
        $this->assertSame(1, $simulation->recipients()->where('status', 'pending')->count());
        $this->assertSame('running', $simulation->fresh()->status);
        Mail::assertSent(SimulationMessage::class, fn (SimulationMessage $mail): bool => $mail->usesMailer(SmtpDelivery::MAILER) && $mail->fromAddress === 'awareness@example.com' && $mail->senderName === 'HRD' && str_contains($mail->trackingLink, '/t/') && ! str_contains($mail->trackingLink, $mail->recipientEmail));
        Queue::assertPushed(ProcessSimulation::class, 1);
        $job = $this->runJob($simulation->fresh());
        $job->assertReleased(5);
        Mail::assertSent(SimulationMessage::class, 1);
    }

    public function test_zero_interval_processes_the_next_recipient_without_an_added_delay(): void
    {
        WorkspaceSetting::factory()->smtp()->create(['values' => [...WorkspaceSetting::factory()->smtp()->make()->values, 'sendIntervalSeconds' => 0]]);
        $simulation = $this->dueSimulation(2);
        Queue::fake([ProcessSimulation::class]);
        Mail::fake();
        $this->runJob($simulation);
        $this->runJob($simulation->fresh());
        $this->runJob($simulation->fresh());
        Mail::assertSent(SimulationMessage::class, 2);
        $this->assertSame(2, $simulation->recipients()->where('status', 'sent')->count());
    }

    public function test_interval_applies_across_two_simulations_using_the_same_smtp(): void
    {
        WorkspaceSetting::factory()->smtp()->create();
        $first = $this->dueSimulation();
        $second = $this->dueSimulation();
        Queue::fake([ProcessSimulation::class]);
        Mail::fake();
        $this->freezeTime();
        $this->runJob($first);
        $this->runJob($second)->assertReleased(5);
        $this->travel(5)->seconds();
        $this->runJob($second->fresh());
        Mail::assertSent(SimulationMessage::class, 2);
    }

    public function test_jobs_do_not_send_before_start_or_at_the_evaluation_deadline(): void
    {
        WorkspaceSetting::factory()->smtp()->create();
        $simulation = $this->dueSimulation();
        $simulation->update(['starts_at' => now()->addMinute()]);
        Mail::fake();
        $this->runJob($simulation)->assertReleased(60);
        $simulation->update(['starts_at' => now()->subHour(), 'ends_at' => now()]);
        $this->runJob($simulation->fresh());
        $this->assertSame('completed', $simulation->fresh()->status);
        $this->assertSame('skipped', $simulation->recipients()->sole()->status);
        Mail::assertNothingOutgoing();
    }

    public function test_completed_delivery_stays_running_until_the_evaluation_deadline(): void
    {
        WorkspaceSetting::factory()->smtp()->create();
        $simulation = $this->dueSimulation();
        Mail::fake();
        Queue::fake([ProcessSimulation::class]);
        $this->runJob($simulation);
        $this->assertSame('running', $simulation->fresh()->status);
        $this->travelTo($simulation->ends_at);
        $this->artisan('simulations:dispatch')->assertSuccessful();
        $this->assertSame('completed', $simulation->fresh()->status);
    }

    public function test_unconfigured_smtp_waits_without_claiming_a_recipient(): void
    {
        $simulation = $this->dueSimulation();
        Mail::fake();
        $this->runJob($simulation)->assertReleased(60);
        $this->assertSame('pending', $simulation->recipients()->sole()->status);
        Mail::assertNothingOutgoing();
    }

    public function test_detail_shows_expired_evaluation_as_completed_before_the_next_scheduler_tick(): void
    {
        $this->actingAs(User::factory()->create());
        $simulation = $this->dueSimulation();
        $simulation->update(['ends_at' => now()->subSecond()]);

        $this->getJson('/simulasi/'.$simulation->id)->assertOk()
            ->assertJsonPath('data.status', 'completed')->assertJsonPath('data.pending', 0)
            ->assertJsonPath('data.skipped', 1)->assertJsonPath('data.recipientRows.0.status', 'skipped');
    }

    public function test_an_uncertain_smtp_attempt_is_never_retried_for_the_same_recipient(): void
    {
        WorkspaceSetting::factory()->smtp()->create();
        $simulation = $this->dueSimulation();
        $this->mock(SmtpDelivery::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')->once()->andThrow(new TransportException('Secret SMTP-password-for-tests'));
        });
        $this->runJob($simulation);
        $this->travel(10)->seconds();
        $this->runJob($simulation->fresh());
        $recipient = $simulation->recipients()->sole();
        $this->assertSame('uncertain', $recipient->status);
        $this->assertStringNotContainsString('SMTP-password-for-tests', $recipient->error);
    }

    public function test_stale_in_flight_attempts_are_recorded_as_uncertain_without_resending(): void
    {
        WorkspaceSetting::factory()->smtp()->create();
        $simulation = $this->dueSimulation();
        $simulation->recipients()->update(['status' => 'sending', 'attempted_at' => now()->subMinutes(6)]);
        Mail::fake();
        $this->runJob($simulation);
        $this->assertSame('uncertain', $simulation->recipients()->sole()->status);
        Mail::assertNothingOutgoing();
    }

    public function test_global_delivery_lock_prevents_parallel_sends(): void
    {
        WorkspaceSetting::factory()->smtp()->create();
        $simulation = $this->dueSimulation();
        Mail::fake();
        $lock = Cache::lock('awareness-smtp-delivery', 300);
        $lock->get();
        try {
            $this->runJob($simulation)->assertReleased(2);
            Mail::assertNothingOutgoing();
        } finally {
            $lock->release();
        }
    }

    public function test_scheduler_recovers_due_work_and_does_not_dispatch_drafts_or_future_schedules(): void
    {
        $due = $this->dueSimulation();
        Simulation::factory()->create();
        Simulation::factory()->scheduled()->create();
        Queue::fake([ProcessSimulation::class]);
        $this->artisan('simulations:dispatch')->assertSuccessful();
        Queue::assertPushed(ProcessSimulation::class, fn (ProcessSimulation $job): bool => $job->simulationId === $due->id);
        Queue::assertPushed(ProcessSimulation::class, 1);
    }

    public function test_personal_link_records_actual_ip_and_repeat_accesses_without_inflating_unique_clicks(): void
    {
        $simulation = $this->dueSimulation();
        $recipient = $simulation->recipients()->sole();
        $recipient->update(['status' => 'sent', 'sent_at' => now()]);
        $url = route('simulation-links.show', ['token' => $recipient->token]);
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])->withHeaders(['User-Agent' => 'Browser test'])->get($url)->assertOk()->assertDontSee($recipient->email);
        $this->get($url)->assertOk();
        $this->assertDatabaseCount('simulation_clicks', 2);
        $this->assertDatabaseHas('simulation_clicks', ['ip' => '10.20.30.40', 'period' => 'within', 'user_agent' => 'Browser test']);
        $this->actingAs(User::factory()->create())->getJson('/simulasi/'.$simulation->id)->assertOk()->assertJsonPath('data.clicked', 1)->assertJsonPath('data.recipientRows.0.clicks', 2);
        $this->travelTo($simulation->ends_at->addSecond());
        $this->get($url)->assertOk();
        $this->assertDatabaseHas('simulation_clicks', ['period' => 'after']);
        $this->getJson('/simulasi/'.$simulation->id)->assertJsonPath('data.clicked', 1)->assertJsonPath('data.recipientRows.0.afterClicks', 1);
    }

    public function test_head_requests_and_unsent_or_unknown_links_do_not_create_click_evidence(): void
    {
        $simulation = $this->dueSimulation();
        $recipient = $simulation->recipients()->sole();
        $url = route('simulation-links.show', ['token' => $recipient->token]);
        $this->get($url)->assertNotFound();
        $recipient->update(['status' => 'sent']);
        $this->call('HEAD', $url)->assertOk();
        $this->get('/t/'.str_repeat('a', 64))->assertNotFound();
        $this->assertDatabaseCount('simulation_clicks', 0);
    }

    public function test_real_email_html_preserves_formatting_resolves_split_tokens_and_escapes_authored_text(): void
    {
        $mail = new SimulationMessage('Subject', ['ops' => [['insert' => '<script>bad</script>', 'attributes' => ['bold' => true]], ['insert' => "\n"], ['insert' => '{{tracking_', 'attributes' => ['italic' => true]], ['insert' => "link}}\n"]]], 'employee@example.com', 'Payroll', 'https://awareness.example.com/t/personal', 'hr@example.com', 'HRD');
        $mail->assertSeeInHtml('<strong>&lt;script&gt;bad&lt;/script&gt;</strong>', false);
        $mail->assertDontSeeInHtml('<script>bad</script>', false);
        $mail->assertSeeInHtml('href="https://awareness.example.com/t/personal"', false);
        $mail->assertDontSeeInHtml('{{tracking_', false);
        $mail->assertSeeInText('https://awareness.example.com/t/personal');
    }
}
