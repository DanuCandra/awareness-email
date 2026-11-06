<?php

namespace Tests\Feature;

use App\Mail\SmtpTestMessage;
use App\Models\User;
use App\Models\WorkspaceSetting;
use App\SmtpDelivery;
use Database\Seeders\WorkspaceSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class WorkspaceSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function smtpData(): array
    {
        return [
            'host' => 'smtp.example.com', 'port' => '587', 'encryption' => 'starttls', 'username' => 'smtp-user',
            'password' => 'Test-password with spaces ', 'fromEmail' => ' MAIL@EXAMPLE.COM ', 'fromName' => 'HRD Perusahaan',
            'replyTo' => 'HELPDESK@EXAMPLE.COM', 'sendIntervalSeconds' => '5',
        ];
    }

    public function test_settings_and_smtp_tests_require_login(): void
    {
        Mail::fake();

        $this->get('/settings')->assertRedirect(route('login'));
        $this->getJson('/settings')->assertUnauthorized();
        $this->putJson('/settings/smtp', $this->smtpData())->assertUnauthorized();
        $this->postJson('/settings/smtp/test-connection')->assertUnauthorized();
        $this->postJson('/settings/smtp/test-email')->assertUnauthorized();

        $this->assertDatabaseCount('workspace_settings', 0);
        Mail::assertNothingOutgoing();
    }

    public function test_settings_start_with_no_configured_smtp_and_no_browser_sample_values(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/settings')->assertOk()
            ->assertJsonPath('data.smtp.host', '')
            ->assertJsonPath('data.smtp.fromEmail', '')
            ->assertJsonPath('data.smtp.configured', false)
            ->assertJsonPath('data.smtp.passwordConfigured', false)
            ->assertJsonPath('data.smtp.sendIntervalSeconds', 0);

        $this->assertDatabaseCount('workspace_settings', 0);
    }

    public function test_smtp_password_is_encrypted_preserved_exactly_and_never_returned_to_the_browser(): void
    {
        $this->actingAs(User::factory()->create());
        $data = $this->smtpData();

        $response = $this->putJson('/settings/smtp', [...$data, 'verify_peer' => false, 'transport' => 'log', 'secret' => 'injected']);
        $response->assertOk()->assertJsonPath('data.smtp.configured', true)
            ->assertJsonPath('data.smtp.fromEmail', 'mail@example.com')->assertJsonPath('data.smtp.replyTo', 'helpdesk@example.com')
            ->assertJsonPath('data.smtp.sendIntervalSeconds', 5)->assertJsonMissingPath('data.smtp.password')->assertJsonMissingPath('data.smtp.secret');
        $row = WorkspaceSetting::sole();
        $encrypted = $row->getRawOriginal('secret');
        $this->assertNotSame($data['password'], $encrypted);
        $this->assertSame($data['password'], Crypt::decryptString($encrypted));
        $this->assertSame($data['password'], $row->secret);
        $this->assertArrayNotHasKey('secret', $row->toArray());
        $this->assertArrayNotHasKey('verify_peer', $row->values);
        $this->assertArrayNotHasKey('password', $row->values);
        $this->assertStringNotContainsString($data['password'], $response->getContent());
        $this->assertStringNotContainsString($data['password'], $this->get('/settings')->assertOk()->getContent());
    }

    public function test_empty_password_keeps_credentials_and_zero_interval_is_persisted(): void
    {
        $this->actingAs(User::factory()->create());
        $row = WorkspaceSetting::factory()->smtp()->create();
        $secret = $row->getRawOriginal('secret');

        $this->putJson('/settings/smtp', [...$this->smtpData(), 'password' => '', 'sendIntervalSeconds' => 0])
            ->assertOk()->assertJsonPath('data.smtp.sendIntervalSeconds', 0);

        $this->assertSame($secret, $row->fresh()->getRawOriginal('secret'));
        $this->assertSame(0, $row->fresh()->values['sendIntervalSeconds']);
    }

    public function test_first_smtp_configuration_requires_a_password(): void
    {
        $this->actingAs(User::factory()->create());

        $this->putJson('/settings/smtp', [...$this->smtpData(), 'password' => ''])->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'Isi password SMTP untuk konfigurasi pertama.');

        $this->assertDatabaseCount('workspace_settings', 0);
    }

    public function test_changing_the_smtp_host_requires_password_reentry_and_does_not_disclose_the_old_secret(): void
    {
        $this->actingAs(User::factory()->create());
        $row = WorkspaceSetting::factory()->smtp()->create();

        $this->putJson('/settings/smtp', [...$this->smtpData(), 'host' => 'other.example.com', 'password' => ''])
            ->assertUnprocessable()->assertJsonPath('errors.password.0', 'Isi ulang password SMTP saat mengganti host atau username.');

        $this->assertSame('smtp.example.com', $row->fresh()->values['host']);
        $this->assertSame('SMTP-password-for-tests', $row->fresh()->secret);
    }

    public function test_a_new_password_replaces_the_existing_encrypted_password(): void
    {
        $this->actingAs(User::factory()->create());
        $row = WorkspaceSetting::factory()->smtp()->create();

        $this->putJson('/settings/smtp', $this->smtpData())->assertOk();

        $this->assertSame('Test-password with spaces ', $row->fresh()->secret);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidSmtpValues(): array
    {
        return [
            'host URL' => [['host' => 'https://smtp.example.com'], 'host'],
            'invalid host label' => [['host' => 'smtp..example.com'], 'host'],
            'host command injection' => [['host' => "smtp.example.com\r\nEHLO unsafe"], 'host'],
            'zero port' => [['port' => 0], 'port'],
            'oversized port' => [['port' => 65536], 'port'],
            'plaintext transport' => [['encryption' => 'none'], 'encryption'],
            'invalid sender' => [['fromEmail' => 'invalid'], 'fromEmail'],
            'invalid reply address' => [['replyTo' => 'invalid'], 'replyTo'],
            'header injection' => [['fromName' => "HRD\r\nBcc: other@example.com"], 'fromName'],
            'empty username' => [['username' => ''], 'username'],
            'negative interval' => [['sendIntervalSeconds' => -1], 'sendIntervalSeconds'],
            'fractional interval' => [['sendIntervalSeconds' => 1.5], 'sendIntervalSeconds'],
            'oversized interval' => [['sendIntervalSeconds' => '9999999999999999'], 'sendIntervalSeconds'],
        ];
    }

    #[DataProvider('invalidSmtpValues')]
    public function test_invalid_smtp_values_cannot_be_saved(array $changes, string $field): void
    {
        $this->actingAs(User::factory()->create());

        $this->putJson('/settings/smtp', array_replace($this->smtpData(), $changes))->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('workspace_settings', 0);
    }

    public function test_settings_sections_are_shared_by_admins_and_saved_independently(): void
    {
        $this->actingAs(User::factory()->create());
        $smtp = WorkspaceSetting::factory()->smtp()->create();

        $this->putJson('/settings/identity', ['companyName' => 'Perusahaan Bersama', 'contactEmail' => 'IT@EXAMPLE.COM', 'logo' => ''])
            ->assertOk()->assertJsonPath('data.identity.contactEmail', 'it@example.com');
        $this->putJson('/settings/reports', ['pageLength' => 25, 'period' => '30', 'timezone' => 'UTC'])
            ->assertOk()->assertJsonPath('data.reports.timezone', 'Asia/Jakarta');
        $this->putJson('/settings/audit', ['retentionMonths' => 12])->assertOk();

        $this->actingAs(User::factory()->create())->getJson('/settings')->assertOk()
            ->assertJsonPath('data.identity.companyName', 'Perusahaan Bersama')
            ->assertJsonPath('data.reports.pageLength', 25)->assertJsonPath('data.audit.retentionMonths', 12);
        $this->assertSame('SMTP-password-for-tests', $smtp->fresh()->secret);
        $this->assertSame('smtp.example.com', $smtp->fresh()->values['host']);
    }

    public function test_simulation_form_receives_the_saved_sender_without_smtp_passwords(): void
    {
        $this->actingAs(User::factory()->create());
        WorkspaceSetting::factory()->smtp()->create();

        $response = $this->get('/simulasi/buat');

        $response->assertOk()->assertSee('awareness@example.com', false)->assertSee('workspace-settings', false);
        $response->assertDontSee('SMTP-password-for-tests', false);
    }

    public function test_unknown_sections_do_not_create_arbitrary_settings(): void
    {
        $this->actingAs(User::factory()->create());

        $this->putJson('/settings/admin-access', ['role' => 'owner'])->assertNotFound();

        $this->assertDatabaseCount('workspace_settings', 0);
    }

    /** @return array<string, array{string}> */
    public static function invalidLandingUrls(): array
    {
        return [
            'executable scheme' => ['javascript:alert(1)'],
            'embedded credentials' => ['https://user:secret@example.com'],
            'query' => ['https://example.com?token=secret'],
            'fragment' => ['https://example.com#fragment'],
        ];
    }

    #[DataProvider('invalidLandingUrls')]
    public function test_unsafe_landing_addresses_are_rejected(string $url): void
    {
        $this->actingAs(User::factory()->create());

        $this->putJson('/settings/landing', [...config('awareness.defaults.landing'), 'baseUrl' => $url])
            ->assertUnprocessable()->assertJsonValidationErrors('baseUrl');

        $this->assertDatabaseCount('workspace_settings', 0);
    }

    public function test_landing_content_and_report_preferences_are_validated_and_persisted(): void
    {
        $this->actingAs(User::factory()->create());

        $this->putJson('/settings/landing', [...config('awareness.defaults.landing'), 'baseUrl' => 'https://awareness.example.com/', 'tips' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('tips');
        $this->putJson('/settings/landing', [...config('awareness.defaults.landing'), 'baseUrl' => 'https://awareness.example.com/', 'type' => 'confirmation', 'tips' => ''])
            ->assertOk()->assertJsonPath('data.landing.baseUrl', 'https://awareness.example.com')->assertJsonPath('data.landing.tips', '');
        $this->putJson('/settings/reports', ['pageLength' => 999, 'period' => '365'])
            ->assertUnprocessable()->assertJsonValidationErrors(['pageLength', 'period']);
        $this->putJson('/settings/audit', ['retentionMonths' => 1])->assertUnprocessable()->assertJsonValidationErrors('retentionMonths');

        $this->assertDatabaseCount('workspace_settings', 1);
    }

    public function test_logo_must_contain_actual_image_bytes_and_can_be_removed(): void
    {
        $this->actingAs(User::factory()->create());
        $validLogo = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j7f8AAAAASUVORK5CYII=';

        $this->putJson('/settings/identity', ['companyName' => 'Company', 'logo' => 'data:image/png;base64,'.base64_encode('<script>alert(1)</script>')])
            ->assertUnprocessable()->assertJsonValidationErrors('logo');
        $this->putJson('/settings/identity', ['companyName' => 'Company', 'logo' => $validLogo])->assertOk()->assertJsonPath('data.identity.logo', $validLogo);
        $this->putJson('/settings/identity', ['companyName' => 'Company', 'logo' => ''])->assertOk()->assertJsonPath('data.identity.logo', '');

        $this->assertSame('', WorkspaceSetting::sole()->values['logo']);
    }

    public function test_connection_test_records_success_for_the_saved_configuration_without_sending_mail(): void
    {
        $this->travelTo('2026-11-06 13:00:00');
        $this->actingAs(User::factory()->create());
        $smtp = WorkspaceSetting::factory()->smtp()->create(['updated_at' => now()->subDay()]);
        Mail::fake();
        $this->mock(SmtpDelivery::class, function (MockInterface $mock) use ($smtp): void {
            $mock->shouldReceive('testConnection')->once()->withArgs(fn (WorkspaceSetting $row): bool => $row->is($smtp));
        });

        $this->postJson('/settings/smtp/test-connection', ['revision' => $smtp->revision])
            ->assertOk()->assertJsonPath('settings.smtp.connectionSuccessful', true);

        $this->assertTrue($smtp->fresh()->connection_successful);
        $this->assertSame(now()->toISOString(), $smtp->fresh()->connection_checked_at->toISOString());
        $this->assertSame(now()->subDay()->toISOString(), $smtp->fresh()->updated_at->toISOString());
        Mail::assertNothingOutgoing();
    }

    public function test_failed_connection_returns_a_redacted_message_and_records_failure(): void
    {
        $this->actingAs(User::factory()->create());
        $smtp = WorkspaceSetting::factory()->smtp()->create();
        $this->mock(SmtpDelivery::class, function (MockInterface $mock): void {
            $mock->shouldReceive('testConnection')->once()->andThrow(new TransportException('Password SMTP-password-for-tests rejected by smtp-user'));
        });

        $response = $this->postJson('/settings/smtp/test-connection', ['revision' => $smtp->revision]);
        $response->assertUnprocessable()->assertJsonValidationErrors('smtp');

        $this->assertStringNotContainsString('SMTP-password-for-tests', $response->getContent());
        $this->assertFalse($smtp->fresh()->connection_successful);
    }

    public function test_connection_status_is_invalidated_when_smtp_settings_change(): void
    {
        $this->actingAs(User::factory()->create());
        $smtp = WorkspaceSetting::factory()->smtp()->create(['connection_successful' => true, 'connection_checked_at' => now()]);
        $revision = $smtp->revision;

        $this->putJson('/settings/smtp', [...$this->smtpData(), 'password' => ''])
            ->assertOk()->assertJsonPath('data.smtp.connectionSuccessful', null);

        $this->assertNotSame($revision, $smtp->fresh()->revision);
        $this->assertNull($smtp->fresh()->connection_checked_at);
    }

    public function test_stale_test_requests_and_unconfigured_smtp_cannot_send_mail(): void
    {
        $this->actingAs(User::factory()->create());
        Mail::fake();
        $this->postJson('/settings/smtp/test-email', ['revision' => (string) Str::uuid(), 'recipient' => 'it@example.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('smtp');
        WorkspaceSetting::factory()->smtp()->create();

        $this->postJson('/settings/smtp/test-email', ['revision' => (string) Str::uuid(), 'recipient' => 'it@example.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('smtp');

        Mail::assertNothingOutgoing();
    }

    public function test_test_email_uses_only_the_saved_smtp_sender_and_clears_runtime_credentials(): void
    {
        $this->actingAs(User::factory()->create());
        $smtp = WorkspaceSetting::factory()->smtp()->create();
        Mail::fake();

        $this->postJson('/settings/smtp/test-email', [
            'revision' => $smtp->revision, 'recipient' => 'IT@EXAMPLE.COM',
            'fromEmail' => 'spoofed@example.com', 'host' => 'attacker.example.com',
        ])->assertOk()->assertJsonPath('message', 'Server SMTP menerima email percobaan untuk it@example.com. Periksa kotak masuk atau spam; penerimaan di inbox bergantung pada penyedia email.');

        Mail::assertSent(SmtpTestMessage::class, function (SmtpTestMessage $mail): bool {
            return $mail->hasTo('it@example.com') && $mail->usesMailer(SmtpDelivery::MAILER) && $mail->fromAddress === 'awareness@example.com'
                && $mail->senderName === 'Tim IT' && $mail->replyAddress === 'helpdesk@example.com';
        });
        $this->assertNull(config('mail.mailers.'.SmtpDelivery::MAILER));
        $this->assertSame('smtp.example.com', $smtp->fresh()->values['host']);
        $this->assertTrue($smtp->fresh()->connection_successful);
        $this->assertNotNull($smtp->fresh()->connection_checked_at);
    }

    public function test_invalid_test_recipient_is_rejected_without_sending(): void
    {
        $this->actingAs(User::factory()->create());
        $smtp = WorkspaceSetting::factory()->smtp()->create();
        Mail::fake();

        $this->postJson('/settings/smtp/test-email', ['revision' => $smtp->revision, 'recipient' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('recipient');

        Mail::assertNothingOutgoing();
    }

    public function test_failed_test_email_returns_a_redacted_error(): void
    {
        $this->actingAs(User::factory()->create());
        $smtp = WorkspaceSetting::factory()->smtp()->create();
        $this->mock(SmtpDelivery::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendTest')->once()->andThrow(new TransportException('SMTP-password-for-tests was rejected'));
        });

        $response = $this->postJson('/settings/smtp/test-email', ['revision' => $smtp->revision, 'recipient' => 'it@example.com']);
        $response->assertUnprocessable()->assertJsonValidationErrors('smtp');

        $this->assertStringNotContainsString('SMTP-password-for-tests', $response->getContent());
        $this->assertNull($smtp->fresh()->connection_successful);
    }

    public function test_testing_an_old_revision_does_not_mark_new_smtp_settings_as_verified(): void
    {
        $this->actingAs(User::factory()->create());
        $smtp = WorkspaceSetting::factory()->smtp()->create();
        $this->mock(SmtpDelivery::class, function (MockInterface $mock): void {
            $mock->shouldReceive('testConnection')->once()->andReturnUsing(function (WorkspaceSetting $row): void {
                WorkspaceSetting::query()->whereKey($row->id)->update(['revision' => (string) Str::uuid()]);
            });
        });

        $this->postJson('/settings/smtp/test-connection', ['revision' => $smtp->revision])->assertOk()
            ->assertJsonPath('configurationChanged', true)->assertJsonPath('settings.smtp.connectionSuccessful', null);

        $this->assertNull($smtp->fresh()->connection_checked_at);
    }

    public function test_smtp_network_tests_are_rate_limited_per_admin(): void
    {
        $this->actingAs(User::factory()->create());
        $smtp = WorkspaceSetting::factory()->smtp()->create();
        $this->mock(SmtpDelivery::class, function (MockInterface $mock): void {
            $mock->shouldReceive('testConnection')->times(5);
        });

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/settings/smtp/test-connection', ['revision' => $smtp->revision])->assertOk();
        }
        $this->postJson('/settings/smtp/test-connection', ['revision' => $smtp->revision])->assertTooManyRequests();
    }

    public function test_smtp_settings_use_required_tls_and_the_database_credentials(): void
    {
        $smtp = WorkspaceSetting::factory()->smtp()->make();
        $delivery = app(SmtpDelivery::class);

        $transport = app(MailManager::class)->createSymfonyTransport($delivery->configuration($smtp));

        $this->assertTrue($transport->isTlsRequired());
        $this->assertTrue($transport->isAutoTls());
        $this->assertSame('smtp-user', $transport->getUsername());
        $this->assertSame('SMTP-password-for-tests', $transport->getPassword());
        $smtp->values = [...$smtp->values, 'encryption' => 'tls', 'port' => 465];
        $tls = app(MailManager::class)->createSymfonyTransport($delivery->configuration($smtp));
        $this->assertTrue($tls->getStream()->isTLS());
        $this->assertTrue($tls->isTlsRequired());
    }

    public function test_test_message_identifies_its_purpose_and_escapes_company_content(): void
    {
        $mail = new SmtpTestMessage('<script>unsafe</script>', 'it@example.com', 'Administrator', 'help@example.com');

        $mail->assertFrom('it@example.com', 'Administrator');
        $mail->assertHasReplyTo('help@example.com');
        $mail->assertHasSubject('Email percobaan SMTP — Security Awareness');
        $mail->assertSeeInHtml('Email percobaan SMTP');
        $mail->assertDontSeeInHtml('<script>unsafe</script>', false);
        $mail->assertSeeInText('tidak merekam aktivitas klik');
    }

    public function test_seeding_settings_preserves_existing_smtp_credentials(): void
    {
        $smtp = WorkspaceSetting::factory()->smtp()->create();

        $this->seed(WorkspaceSettingSeeder::class);

        $this->assertDatabaseCount('workspace_settings', 5);
        $this->assertSame('SMTP-password-for-tests', $smtp->fresh()->secret);
        $this->assertSame('smtp.example.com', $smtp->fresh()->values['host']);
    }
}
