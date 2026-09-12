<?php

namespace App\Support;

use App\Http\Resources\MethodCallResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ResourceProvider
{
    public function collection(): AnonymousResourceCollection
    {
        return MethodCallResource::collection([]);
    }

    /** @return array{items: AnonymousResourceCollection} */
    public function groups(): array
    {
        return ['items' => MethodCallResource::collection([])];
    }
}
