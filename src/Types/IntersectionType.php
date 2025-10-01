<?php

namespace Laravel\Surveyor\Types;

class IntersectionType extends AbstractType implements Contracts\MultiType, Contracts\Type
{
    public function __construct(public readonly array $types = [])
    {
        //
    }

    public function id(): string
    {
        return collect($this->types)->toJson();
    }
}
