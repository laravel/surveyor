<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MappedResourceCollection extends ResourceCollection
{
    public $collects = MethodCallResource::class;

    public function toArray(Request $request): array
    {
        $start = $this->additional['start'] ?? null;

        return $this->collection?->map(function ($resource) use ($start) {
            return (new MethodCallResource($resource))->additional(['start' => $start]);
        })->toArray() ?? [];
    }
}
