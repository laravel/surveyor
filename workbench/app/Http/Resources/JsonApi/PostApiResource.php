<?php

namespace App\Http\Resources\JsonApi;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin \App\Models\Post
 */
class PostApiResource extends JsonApiResource
{
    public $attributes = [
        'title',
        'body',
    ];

    public $relationships = [
        'user',
    ];
}
