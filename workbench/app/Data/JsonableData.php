<?php

namespace App\Data;

use Illuminate\Contracts\Support\Jsonable;

class JsonableData implements Jsonable
{
    public function toJson($options = 0): string
    {
        return json_encode([
            'label' => 'example',
            'count' => 1,
            'nested' => ['active' => true],
        ], $options);
    }
}
