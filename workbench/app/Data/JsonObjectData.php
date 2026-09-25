<?php

namespace App\Data;

use Illuminate\Contracts\Support\Jsonable;

class JsonObjectData implements Jsonable
{
    public function toJson($options = 0): string
    {
        return json_encode(flags: JSON_FORCE_OBJECT, value: ['numbers' => [1, 2]]);
    }
}
