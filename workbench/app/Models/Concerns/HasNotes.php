<?php

namespace App\Models\Concerns;

use App\Attributes\ConditionalIgnore;
use App\Attributes\Ignore;
use App\Support\Dimensions;
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

    /**
     * @return Attribute<Dimensions, never>
     */
    protected function traitDimensions(): Attribute
    {
        return Attribute::get(fn (): Dimensions => new Dimensions(1, 2))->withoutObjectCaching();
    }
}
