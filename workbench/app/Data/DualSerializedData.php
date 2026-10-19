<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class DualSerializedData implements Arrayable, JsonSerializable
{
    public function toArray(): array
    {
        return ['from_array' => 1];
    }

    public function jsonSerialize(): array
    {
        return ['from_json' => 'value'];
    }
}
