<?php

namespace App\Models;

use Database\Factories\SimulationRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['simulation_id', 'recipient_id', 'email', 'token', 'status', 'attempted_at', 'sent_at', 'sender_email', 'error'])]
class SimulationRecipient extends Model
{
    /** @use HasFactory<SimulationRecipientFactory> */
    use HasFactory;

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['attempted_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function simulation(): BelongsTo
    {
        return $this->belongsTo(Simulation::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(SimulationClick::class);
    }
}
