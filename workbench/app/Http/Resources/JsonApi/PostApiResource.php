<?php

namespace App\Http\Resources\JsonApi;

use App\Models\Post;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin Post
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
