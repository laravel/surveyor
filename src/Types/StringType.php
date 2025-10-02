<?php

namespace Laravel\Surveyor\Types;

class StringType extends AbstractType implements Contracts\Type
{
    public function __construct(public readonly ?string $value = null)
    {
        //
    }

    public function id(): string
    {
        return $this->value === null ? 'null' : $this->value;
    }
}
