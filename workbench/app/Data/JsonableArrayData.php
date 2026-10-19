<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

class JsonableArrayData implements Arrayable, Jsonable
{
    public function toArray(): array
    {
        return ['from_array' => 1];
    }

    public function toJson($options = 0): string
    {
        return json_encode(['from_json' => 'value'], $options);
    }
}
