<?php

namespace App\Models;

use Database\Factories\SimulationDeliveryLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['simulation_id', 'simulation_recipient_id', 'email', 'event', 'message', 'event_key', 'occurred_at'])]
class SimulationDeliveryLog extends Model
{
    /** @use HasFactory<SimulationDeliveryLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $hidden = ['event_key'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function simulation(): BelongsTo
    {
        return $this->belongsTo(Simulation::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(SimulationRecipient::class, 'simulation_recipient_id');
    }
}
