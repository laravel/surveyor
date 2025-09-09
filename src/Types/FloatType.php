<?php

namespace Laravel\StaticAnalyzer\Types;

class FloatType extends NumberType
{
    public function __construct(public readonly ?float $value = null)
    {
        //
    }

    public function id(): string
    {
        return $this->value === null ? 'null' : (string) $this->value;
    }
}
