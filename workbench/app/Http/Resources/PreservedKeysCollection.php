<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PreservedKeysCollection extends ResourceCollection
{
    public $collects = MethodCallResource::class;

    public $preserveKeys = true;
}
