<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailTemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'category' => $this->category->name,
            'categoryId' => $this->template_category_id,
            'subject' => $this->subject,
            'delta' => $this->delta,
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
