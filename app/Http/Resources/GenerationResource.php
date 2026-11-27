<?php

namespace App\Http\Resources;

use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A generation as the history page shows it. Money is sent as exact decimal
 * strings; the page formats them, it never does arithmetic on them.
 *
 * @mixin Generation
 */
class GenerationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'prompt' => $this->prompt,
            'size' => $this->size->value,
            'quality' => $this->quality->value,
            'reference_images' => $this->reference_images,
            'status' => $this->status->label(),
            'is_final' => $this->status->isFinal(),
            'estimated_price' => $this->estimated_price->toDecimal(),
            'estimated_price_max' => $this->estimated_price_max->toDecimal(),
            'price' => $this->status->isFinal() ? $this->price?->toDecimal() : null,
            'image_url' => $this->hasImage() ? route('generations.image', $this->resource) : null,
            'error' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }

    private function hasImage(): bool
    {
        return $this->image_path !== null
            || ($this->image_url !== null && $this->image_url_expires_at?->isFuture() === true);
    }
}
