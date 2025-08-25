<?php

namespace Laravel\StaticAnalyzer\Types;

class BoolType extends AbstractType implements Contracts\Type
{
    public function __construct(public readonly ?bool $value = null)
    {
        //
    }

    public function id(): string
    {
        if ($this->value === null) {
            return 'null';
        }

        return $this->value ? 'true' : 'false';
    }
}
