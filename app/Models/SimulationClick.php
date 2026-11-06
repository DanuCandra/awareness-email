<?php

namespace App\Models;

use Database\Factories\SimulationClickFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['simulation_recipient_id', 'ip', 'user_agent', 'clicked_at', 'period'])]
class SimulationClick extends Model
{
    /** @use HasFactory<SimulationClickFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['clicked_at' => 'datetime'];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(SimulationRecipient::class, 'simulation_recipient_id');
    }
}
