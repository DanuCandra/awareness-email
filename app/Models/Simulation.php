<?php

namespace App\Models;

use Database\Factories\SimulationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['created_by', 'client_token', 'dispatch_version', 'name', 'description', 'status', 'sender_name', 'sender_email', 'reply_to', 'template_id', 'subject', 'delta', 'recipient_mode', 'starts_at', 'ends_at', 'queued_at', 'send_interval_seconds', 'last_error'])]
class Simulation extends Model
{
    /** @use HasFactory<SimulationFactory> */
    use HasFactory, HasUuids;

    protected $hidden = ['client_token', 'dispatch_version'];

    protected function casts(): array
    {
        return ['delta' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'queued_at' => 'datetime', 'send_interval_seconds' => 'integer'];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(SimulationRecipient::class);
    }

    public function effectiveStatus(): string
    {
        return $this->status !== 'draft' && $this->ends_at?->lte(now()) ? 'completed' : $this->status;
    }
}
