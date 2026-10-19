<?php

namespace App\Data;

use Illuminate\Contracts\Support\Jsonable;

class LiteralJsonData implements Jsonable
{
    public function toJson($options = 0): string
    {
        return '{"label":"array","class":"App\\\\Models\\\\Tag","nested":{"active":true},"count":null}';
    }
}
