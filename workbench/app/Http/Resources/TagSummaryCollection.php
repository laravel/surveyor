<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TagSummaryCollection extends ResourceCollection
{
    public $collects = PlainLabelResource::class;

    public function toArray(Request $request): array
    {
        return [
            'items' => $this->collection,
            'count' => $this->collection->count(),
            'summary' => ['source' => 'tags'],
        ];
    }
}
