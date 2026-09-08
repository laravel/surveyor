<?php

namespace Laravel\Surveyor\Types;

class IntersectionType extends AbstractType implements Contracts\MultiType, Contracts\Type
{
    public function __construct(public readonly array $types = [])
    {
        //
    }

    /**
     * @return Contracts\Type[]
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function id(): string
    {
        return json_encode($this->types);
    }
}
