<?php

namespace App\Http\Resources;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tag
 */
class PlainLabelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'label' => $this->label,
        ];
    }
}
