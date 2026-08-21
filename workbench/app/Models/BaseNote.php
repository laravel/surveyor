<?php

namespace App\Models;

use App\Attributes\Ignore;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

abstract class BaseNote extends Model
{
    public function parentPublic(): Attribute
    {
        return Attribute::make(get: fn (): string => 'public');
    }

    #[Ignore]
    public function parentSecret(): Attribute
    {
        return Attribute::make(get: fn (): string => 'secret');
    }
}
