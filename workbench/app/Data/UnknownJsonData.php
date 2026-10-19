<?php

namespace App\Data;

use Illuminate\Contracts\Support\Jsonable;

class UnknownJsonData implements Jsonable
{
    public string $json;

    public function toJson($options = 0): string
    {
        return $this->json;
    }
}
