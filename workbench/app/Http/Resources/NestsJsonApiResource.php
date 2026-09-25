<?php

namespace App\Http\Resources;

use App\Http\Resources\JsonApi\PostApiResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NestsJsonApiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'post' => new PostApiResource($request->boolean('with_post') ? new Post : null),
        ];
    }
}
