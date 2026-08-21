<?php

namespace App\Models\Concerns;

use App\Attributes\ConditionalIgnore;
use App\Attributes\Ignore;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasNotes
{
    public function traitPublic(): Attribute
    {
        return Attribute::make(get: fn (): string => 'public');
    }

    #[Ignore]
    public function traitSecret(): Attribute
    {
        return Attribute::make(get: fn (): string => 'secret');
    }

    #[ConditionalIgnore(unless: 'features.notes')]
    public function traitConditional(): Attribute
    {
        return Attribute::make(get: fn (): string => 'conditional');
    }
}
