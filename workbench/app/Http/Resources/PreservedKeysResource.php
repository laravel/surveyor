<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreservedKeysResource extends JsonResource
{
    public $preserveKeys = true;

    public function toArray(Request $request): array
    {
        return ['id' => 1, 'label' => 'Example'];
    }
}
