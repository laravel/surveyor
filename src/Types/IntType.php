<?php

namespace Laravel\StaticAnalyzer\Types;

class IntType extends NumberType
{
    public function __construct(public readonly ?int $value = null)
    {
        //
    }

    public function id(): string
    {
        return $this->value === null ? 'null' : (string) $this->value;
    }
}
