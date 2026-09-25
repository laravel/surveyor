<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class MetadataTagResource extends PlainLabelResource
{
    public function with(Request $request): array
    {
        return ['meta' => ['version' => '1.0', 'source' => 'resource']];
    }
}
