<?php

namespace App\Data;

use Illuminate\Contracts\Support\Jsonable;

class AlternativeJsonData implements Jsonable
{
    public bool $literal;

    public function toJson($options = 0): string
    {
        if ($this->literal) {
            return '{"count":1}';
        }

        return json_encode(['name' => 'example'], $options | JSON_THROW_ON_ERROR);
    }
}
