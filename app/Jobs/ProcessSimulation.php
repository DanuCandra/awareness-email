<?php

namespace App\Jobs;

use App\Mail\SimulationMessage;
use App\Models\Simulation;
use App\Models\SimulationRecipient;
use App\Models\WorkspaceSetting;
use App\SimulationManager;
use App\SmtpDelivery;
use App\WorkspaceSettings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class ProcessSimulation implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $timeout = 60;

    public function __construct(public string $simulationId, public string $version) {}

    public function handle(SimulationManager $manager, SmtpDelivery $delivery): void
    {
        $simulation = Simulation::query()->whereKey($this->simulationId)->where('dispatch_version', $this->version)->first();
        if (! $simulation || ! in_array($simulation->status, ['scheduled', 'running'], true)) {
            return;
        }
        if ($simulation->ends_at?->lte(now())) {
            $manager->expire($simulation);

            return;
        }
        if ($simulation->starts_at?->gt(now())) {
            $this->release(max(1, (int) ceil($simulation->starts_at->getTimestamp() - now()->getTimestamp())));

            return;
        }
        $lock = Cache::lock('awareness-smtp-delivery', 300);
        if (! $lock->get()) {
            $this->release(2);

            return;
        }
        try {
            $smtp = WorkspaceSetting::query()->where('section', 'smtp')->first();
            if (! $smtp || ! filled($smtp->getRawOriginal('secret')) || empty($smtp->values['host']) || empty($smtp->values['fromEmail'])) {
                $simulation->update(['last_error' => 'Konfigurasi SMTP belum lengkap. Pengiriman menunggu pengaturan SMTP.']);
                $this->release(60);

                return;
            }
            if ($smtp->next_send_at?->gt(now())) {
                $this->release(max(1, (int) ceil($smtp->next_send_at->getTimestamp() - now()->getTimestamp())));

                return;
            }
            $simulation->recipients()->where('status', 'sending')->where('attempted_at', '<=', now()->subMinutes(5))->update(['status' => 'uncertain', 'error' => 'Hasil proses sebelumnya belum terkonfirmasi. Pengiriman tidak diulang otomatis.']);
            $recipient = DB::transaction(function () use ($smtp): ?SimulationRecipient {
                $current = Simulation::query()->lockForUpdate()->find($this->simulationId);
                if (! $current || $current->dispatch_version !== $this->version || ! in_array($current->status, ['scheduled', 'running'], true) || $current->ends_at?->lte(now())) {
                    return null;
                }
                $recipient = $current->recipients()->where('status', 'pending')->orderBy('id')->lockForUpdate()->first();
                if (! $recipient) {
                    return null;
                }
                $recipient->update(['status' => 'sending', 'attempted_at' => now(), 'sender_email' => $smtp->values['fromEmail']]);
                $current->update(['status' => 'running', 'sender_email' => $smtp->values['fromEmail'], 'reply_to' => $smtp->values['replyTo'] ?? '', 'send_interval_seconds' => (int) $smtp->values['sendIntervalSeconds'], 'last_error' => null]);

                return $recipient;
            });
            if (! $recipient) {
                return;
            }
            $simulation->refresh();
            $settings = app(WorkspaceSettings::class)->all();
            $link = rtrim($settings['landing']['baseUrl'], '/').route('simulation-links.show', ['token' => $recipient->token], false);
            try {
                $delivery->sendMessage($smtp, $recipient->email, new SimulationMessage(
                    $simulation->subject, $simulation->delta, $recipient->email, $simulation->name, $link, $smtp->values['fromEmail'], $simulation->sender_name, $smtp->values['replyTo'] ?? '',
                ));
                $recipient->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
            } catch (Throwable $error) {
                $recipient->update(['status' => 'uncertain', 'error' => 'Pengiriman belum terkonfirmasi. Periksa server SMTP; email ini tidak dikirim ulang otomatis.']);
                $simulation->update(['last_error' => 'Ada pengiriman yang belum terkonfirmasi. Periksa SMTP dan daftar penerima.']);
                if (! $error instanceof TransportExceptionInterface && ! $error instanceof DecryptException) {
                    report(new \RuntimeException('Proses email simulasi '.$simulation->id.' tidak terkonfirmasi ('.$error::class.').'));
                }
            }
            $currentSmtp = $smtp->fresh();
            $next = now()->addSeconds((int) ($currentSmtp?->values['sendIntervalSeconds'] ?? 0));
            WorkspaceSetting::withoutTimestamps(fn (): int => WorkspaceSetting::query()->whereKey($smtp->id)->update(['next_send_at' => $next]));
            if ($simulation->ends_at?->lte(now())) {
                $manager->expire($simulation);
            } elseif ($simulation->recipients()->where('status', 'pending')->exists()) {
                $manager->enqueue($simulation, $next);
            }
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        Simulation::query()->whereKey($this->simulationId)->where('dispatch_version',$this->version)->update(['last_error' => 'Worker berhenti sebelum proses selesai. Antrean akan dipulihkan oleh penjadwal.']);
    }
}
