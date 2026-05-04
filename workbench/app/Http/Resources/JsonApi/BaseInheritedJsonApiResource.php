<?php

namespace App\Http\Resources\JsonApi;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

abstract class BaseInheritedJsonApiResource extends JsonApiResource
{
    public $attributes = ['title', 'body'];

    public $relationships = ['user'];
}
