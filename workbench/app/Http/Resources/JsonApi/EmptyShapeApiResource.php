<?php

namespace App\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class EmptyShapeApiResource extends JsonApiResource
{
    public $attributes = ['title'];

    public function toLinks(Request $request): array
    {
        return [];
    }

    public function toMeta(Request $request): array
    {
        return [];
    }
}
