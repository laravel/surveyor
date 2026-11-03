<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MethodCallResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => 1, 'label' => 'Example'];
    }
}
