<?php

namespace Laravel\StaticAnalyzer\Types;

class ArrayShapeType extends AbstractType implements Contracts\Type
{
    public function __construct(
        public readonly Contracts\Type $keyType,
        public readonly Contracts\Type $valueType,
    ) {
        //
    }

    public function id(): string
    {
        return $this->keyType . ':' . $this->valueType;
    }
}
