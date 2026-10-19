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
        return $this->value === null ? 'string' : $this->value;
    }

    public function isMoreSpecificThan(Contracts\Type $type): bool
    {
        if ($type::class !== self::class) {
            return false;
        }

        return $this->value !== null && $type->value === null;
    }
}
