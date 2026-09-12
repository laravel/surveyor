<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CustomAttributesCollection extends ResourceCollection
{
    public $collects = ConditionalShapeResource::class;

    public function toAttributes(Request $request): array
    {
        return ['attributes' => 42];
    }
}
