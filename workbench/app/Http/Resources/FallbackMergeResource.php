<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FallbackMergeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'before' => 'first',
            $this->mergeWhen($request->boolean('details'), [
                'before' => 1,
                'after' => 1,
                'optional' => 1,
            ]),
            'after' => 'fallback',
            'optional' => $this->when($request->boolean('fallback'), 'fallback'),
        ];
    }
}
