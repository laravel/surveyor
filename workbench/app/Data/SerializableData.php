<?php

namespace App\Data;

use JsonSerializable;

class SerializableData implements JsonSerializable
{
    public function jsonSerialize(): mixed
    {
        return ['label' => 'example'];
    }
}
