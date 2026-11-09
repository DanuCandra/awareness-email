<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SimulationDeliveryLogResource extends JsonResource
{
    /** @return array{id: int, event: string, email: ?string, message: string, at: string} */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'event' => $this->event, 'email' => $this->email, 'message' => $this->message, 'at' => $this->occurred_at->toISOString()];
    }
}
