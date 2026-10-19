<?php

namespace App\Http\Resources;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tag */
class LoadedValueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'loaded' => $this->whenLoaded('label', 'loaded'),
            'loaded_itself' => $this->whenLoaded('label', null, 0),
            'counted' => $this->whenCounted('tags', fn (int $count): string => "{$count} tags"),
            'aggregated' => $this->whenAggregated('tags', 'id', 'max', fn ($max): int => (int) $max),
            'exists' => $this->whenExistsLoaded('tags', 'yes'),
        ];
    }
}
