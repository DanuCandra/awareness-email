<?php

namespace App\Console\Commands;

use App\Models\Simulation;
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
        Simulation::query()->whereIn('status', ['scheduled', 'running'])->where('ends_at', '<=', now())->chunkById(100, function (Collection $rows) use ($manager): void {
            foreach ($rows as $row) {
                $manager->expire($row);
            }
        });
        Simulation::query()->whereIn('status', ['scheduled', 'running'])->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->where(fn (Builder $q): Builder => $q->whereNull('queued_at')->orWhere('queued_at', '<=', now()->subMinutes(5)))
            ->whereHas('recipients', fn (Builder $q): Builder => $q->whereIn('status', ['pending', 'sending']))
            ->chunkById(100, function ($rows) use ($manager): void {
                foreach ($rows as $row) {
                    $manager->enqueue($row, now());
                }
            });

        return self::SUCCESS;
    }
}
