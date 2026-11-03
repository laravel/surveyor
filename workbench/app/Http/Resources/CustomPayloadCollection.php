<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CustomPayloadCollection extends ResourceCollection
{
    public $collects = ConditionalShapeResource::class;

    public function toArray(Request $request): array
    {
        return ['count' => 42];
    }
}
