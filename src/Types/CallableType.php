<?php

namespace Laravel\Surveyor\Types;

class CallableType extends AbstractType
{
    public readonly Contracts\Type $returnType;

    public function __construct(
        public readonly array $parameters,
        ?Contracts\Type $returnType = null
    ) {
        $this->returnType = $returnType ?? Type::mixed();
    }

    public function isMoreSpecificThan(Contracts\Type $type): bool
    {
        if (! $type instanceof CallableType) {
            return false;
        }

        return count($this->parameters) > count($type->parameters);
    }

    public function id(): string
    {
        return json_encode($this->parameters);
    }
}
