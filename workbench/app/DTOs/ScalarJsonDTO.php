<?php

namespace App\DTOs;

use JsonSerializable;

class ScalarJsonDTO implements JsonSerializable
{
    public function jsonSerialize(): string
    {
        return 'customer-123';
    }
}
