<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CustomPaginationCollection extends ResourceCollection
{
    public $collects = PlainLabelResource::class;

    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return ['pagination' => ['page' => $paginated['current_page']]];
    }
}
