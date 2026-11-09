<?php

namespace App\Console\Commands;

use App\Models\Simulation;
use App\SimulationDeliveryLogger;
use App\SimulationManager;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DispatchSimulations extends Command
{
    protected $signature = 'simulations:dispatch';

    protected $description = 'Pulihkan antrean simulasi yang jatuh tempo dan tutup evaluasi yang berakhir';

    public function handle(SimulationManager $manager): int
    {
        Simulation::query()->where(fn (Builder $q): Builder => $q->whereIn('status', ['scheduled', 'running'])
            ->orWhere(fn (Builder $completed): Builder => $completed->where('status', 'completed')->whereHas('recipients', fn (Builder $recipients): Builder => $recipients->where('status', 'sending'))))
            ->where('ends_at', '<=', now())->chunkById(100, function (Collection $rows) use ($manager): void {
                foreach ($rows as $row) {
                    $manager->expire($row);
                }
            });
        Simulation::query()->whereIn('status', ['scheduled', 'running'])->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->where(fn (Builder $q): Builder => $q->whereNull('queued_at')->orWhere('queued_at', '<=', now()->subMinutes(5)))
            ->whereHas('recipients', fn (Builder $q): Builder => $q->whereIn('status', ['pending', 'sending']))
            ->chunkById(100, function ($rows) use ($manager): void {
                foreach ($rows as $row) {
                    $previousQueueTime = $row->queued_at?->getTimestamp() ?? 'initial';
                    $manager->enqueue($row, now());
                    app(SimulationDeliveryLogger::class)->record($row, 'queued', 'Penjadwal memulihkan antrean Simulasi yang jatuh tempo.', key: 'recovery-'.$previousQueueTime);
                }
            });

        return self::SUCCESS;
    }
}
