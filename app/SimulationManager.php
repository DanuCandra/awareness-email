<?php

namespace App;

use App\Jobs\ProcessSimulation;
use App\Models\Recipient;
use App\Models\Simulation;
use App\Models\SimulationRecipient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SimulationManager
{
    public function query(): Builder
    {
        return Simulation::query()->withCount([
            'recipients as target_count',
            'recipients as sent_count' => fn (Builder $q): Builder => $q->where('status', 'sent'),
            'recipients as pending_count' => fn (Builder $q): Builder => $q->where('status', 'pending'),
            'recipients as processing_count' => fn (Builder $q): Builder => $q->where('status', 'sending'),
            'recipients as uncertain_count' => fn (Builder $q): Builder => $q->where('status', 'uncertain'),
            'recipients as skipped_count' => fn (Builder $q): Builder => $q->where('status', 'skipped'),
            'recipients as clicked_count' => fn (Builder $q): Builder => $q->where('status', 'sent')->whereHas('clicks', fn (Builder $c): Builder => $c->where('period', 'within')),
        ]);
    }

    /** @param array<string,mixed> $data */
    public function save(array $data, int $userId, ?Simulation $simulation = null): Simulation
    {
        try {
            return DB::transaction(function () use ($data, $userId, $simulation): Simulation {
                if (! $simulation && isset($data['clientToken'])) {
                    $previous = Simulation::query()->where('client_token', $data['clientToken'])->first();
                    if ($previous) {
                        return $previous;
                    }
                }
                if ($simulation) {
                    $simulation = Simulation::query()->lockForUpdate()->findOrFail($simulation->id);
                    if ($simulation->status !== 'draft') {
                        throw ValidationException::withMessages(['status' => 'Hanya draf yang dapat diedit. Muat ulang detail simulasi.']);
                    }
                } else {
                    $simulation = new Simulation(['created_by' => $userId, 'client_token' => $data['clientToken'] ?? null]);
                }
                $settings = app(WorkspaceSettings::class)->all();
                if ($data['status'] === 'scheduled' && ! $settings['smtp']['configured']) {
                    throw ValidationException::withMessages(['senderEmail' => 'Lengkapi pengaturan SMTP sebelum menjadwalkan simulasi.']);
                }
                $simulation->fill([
                    'name' => $data['name'], 'description' => $data['description'] ?? '',
                    'status' => $data['status'], 'sender_name' => ($data['senderName'] ?? '') ?: $settings['smtp']['fromName'],
                    'sender_email' => $settings['smtp']['fromEmail'], 'reply_to' => $settings['smtp']['replyTo'],
                    'subject' => $data['message']['subject'] ?? '', 'delta' => $data['message']['delta'] ?? ['ops' => [['insert' => "\n"]]],
                    'recipient_mode' => $data['recipientMode'], 'template_id' => $data['templateId'] ?? null,
                    'starts_at' => ! empty($data['scheduledAt']) ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['scheduledAt'], 'Asia/Jakarta')->utc() : null,
                    'ends_at' => ! empty($data['endsAt']) ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['endsAt'], 'Asia/Jakarta')->utc() : null,
                    'dispatch_version' => (string) Str::uuid(), 'send_interval_seconds' => $settings['smtp']['sendIntervalSeconds'], 'last_error' => null,
                ])->save();
                $this->replaceRecipients($simulation, $data['recipientIds'] ?? []);
                if ($simulation->status === 'scheduled') {
                    if (! $simulation->recipients()->exists()) {
                        throw ValidationException::withMessages(['recipients' => 'Daftar penerima kosong atau berubah. Pilih penerima kembali.']);
                    }
                    $this->enqueue($simulation, $simulation->starts_at);
                    app(SimulationDeliveryLogger::class)->record($simulation, 'queued', 'Simulasi dijadwalkan mulai '.$simulation->starts_at->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB.', key: 'scheduled');
                }

                return $simulation;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! $simulation && isset($data['clientToken']) && ($existing = Simulation::query()->where('client_token', $data['clientToken'])->first())) {
                return $existing;
            }
            throw $exception;
        }
    }

    /** @param array<int,int> $ids */
    private function replaceRecipients(Simulation $simulation, array $ids): void
    {
        $simulation->recipients()->delete();
        Recipient::query()->when($simulation->recipient_mode === 'selected', fn (Builder $q): Builder => $q->whereIn('id', $ids))->orderBy('id')->chunkById(300, function (Collection $rows) use ($simulation): void {
            $now = now();
            $simulation->recipients()->insert($rows->map(fn (Recipient $row): array => ['simulation_id' => $simulation->id, 'recipient_id' => $row->id, 'email' => $row->email, 'token' => Str::random(64), 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now])->all());
        });
    }

    public function enqueue(Simulation $simulation, \DateTimeInterface $at): void
    {
        $simulation->forceFill(['queued_at' => $at])->save();
        ProcessSimulation::dispatch($simulation->id, $simulation->dispatch_version)->onConnection(config('awareness.queue_connection', 'database'))->onQueue('simulations')->delay($at)->afterCommit();
    }

    public function duplicate(Simulation $original, int $userId): Simulation
    {
        return DB::transaction(function () use ($original, $userId): Simulation {
            $copy = $original->replicate(['client_token', 'dispatch_version', 'starts_at', 'ends_at', 'queued_at', 'last_error']);
            $copy->fill(['name' => mb_substr('Salinan '.$original->name, 0, 120), 'created_by' => $userId, 'status' => 'draft', 'dispatch_version' => (string) Str::uuid()]);
            $copy->save();
            $this->replaceRecipients($copy, $original->recipients()->whereNotNull('recipient_id')->pluck('recipient_id')->all());

            return $copy;
        });
    }

    public function cancel(Simulation $simulation): Simulation
    {
        return DB::transaction(function () use ($simulation): Simulation {
            $current = Simulation::query()->lockForUpdate()->findOrFail($simulation->id);
            if ($current->status !== 'scheduled') {
                throw ValidationException::withMessages(['status' => 'Jadwal sudah diproses atau tidak lagi dapat dibatalkan.']);
            }
            $current->update(['status' => 'draft', 'dispatch_version' => (string) Str::uuid(), 'queued_at' => null]);
            app(SimulationDeliveryLogger::class)->record($current, 'cancelled', 'Jadwal pengiriman dibatalkan dan Simulasi dikembalikan menjadi draf.', key: 'cancelled');

            return $current;
        });
    }

    public function expire(Simulation $simulation): void
    {
        DB::transaction(function () use ($simulation): void {
            $current = Simulation::query()->lockForUpdate()->find($simulation->id);
            if (! $current || $current->status === 'draft' || ! $current->ends_at?->lte(now())) {
                return;
            }
            $current->update(['status' => 'completed']);
            $skipped = $current->recipients()->where('status', 'pending')->update(['status' => 'skipped', 'error' => 'Batas evaluasi telah berakhir.']);
            $logger = app(SimulationDeliveryLogger::class);
            if ($skipped > 0) {
                $logger->record($current, 'skipped', $skipped.' penerima tidak dikirim karena batas evaluasi telah berakhir.', key: 'deadline-skipped');
            }
            $this->recoverStaleDeliveries($current);
            $logger->record($current, 'evaluation_completed', 'Masa evaluasi Simulasi telah selesai. Tidak ada pengiriman baru setelah batas evaluasi.', key: 'evaluation-completed');
            $logger->finishIfIdle($current);
        });
    }

    public function recoverStaleDeliveries(Simulation $simulation): void
    {
        DB::transaction(function () use ($simulation): void {
            $current = Simulation::query()->lockForUpdate()->find($simulation->id);
            if (! $current || $current->dispatch_version !== $simulation->dispatch_version) {
                return;
            }
            $current->recipients()->where('status', 'sending')->where('attempted_at', '<=', now()->subMinutes(5))
                ->lockForUpdate()->get()->each(function (SimulationRecipient $recipient) use ($current): void {
                    $recipient->update(['status' => 'uncertain', 'error' => 'Hasil proses sebelumnya belum terkonfirmasi; pengiriman tidak diulang otomatis.']);
                    app(SimulationDeliveryLogger::class)->record($current, 'uncertain', 'Proses sebelumnya terhenti tanpa konfirmasi SMTP. Email ini tidak dikirim ulang otomatis.', $recipient, 'stale-'.$recipient->id);
                });
        });
    }
}
