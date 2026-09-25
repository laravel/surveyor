<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AdjustedPaginationCollection extends ResourceCollection
{
    public $collects = PlainLabelResource::class;

    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        $default['links']['custom'] = 'https://example.com';

        return $default;
    }
}
