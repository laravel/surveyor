<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomResolveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['name' => 'Ada'];
    }

    /** @return array{custom: int} */
    public function resolve($request = null): array
    {
        return ['custom' => 42];
    }
}
