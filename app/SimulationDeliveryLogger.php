<?php

namespace App;

use App\Models\Simulation;
use App\Models\SimulationDeliveryLog;
use App\Models\SimulationRecipient;
use Illuminate\Support\Facades\DB;

class SimulationDeliveryLogger
{
    public function record(Simulation $simulation, string $event, string $message, ?SimulationRecipient $recipient = null, ?string $key = null): SimulationDeliveryLog
    {
        $values = [
            'simulation_id' => $simulation->id, 'simulation_recipient_id' => $recipient?->id, 'email' => $recipient?->email,
            'event' => $event, 'message' => $message, 'occurred_at' => now(),
        ];

        if ($key === null) {
            return SimulationDeliveryLog::create($values);
        }

        return SimulationDeliveryLog::firstOrCreate(
            ['event_key' => hash('sha256', $simulation->id.'|'.$simulation->dispatch_version.'|'.$key)],
            $values,
        );
    }

    public function finishIfIdle(Simulation $simulation): void
    {
        DB::transaction(function () use ($simulation): void {
            $current = Simulation::query()->lockForUpdate()->find($simulation->id);
            if (! $current || $current->dispatch_version !== $simulation->dispatch_version || $current->status === 'draft'
                || $current->recipients()->whereIn('status', ['pending', 'sending'])->exists()) {
                return;
            }

            $counts = $current->recipients()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
            $this->record($current, 'delivery_completed',
                'Proses pengiriman selesai. Diterima SMTP: '.($counts['sent'] ?? 0).'; perlu diperiksa: '.($counts['uncertain'] ?? 0).'; tidak dikirim: '.($counts['skipped'] ?? 0).'.',
                key: 'delivery-completed');
        });
    }
}
