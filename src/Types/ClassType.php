<?php

namespace Laravel\Surveyor\Types;

use Laravel\Surveyor\Support\Util;

class ClassType extends AbstractType implements Contracts\Type
{
    public readonly string $value;

    protected array $genericTypes = [];

    protected array $constructorArguments = [];

    public function __construct(string $value)
    {
        $this->value = ltrim($value, '\\');
    }

    public function setConstructorArguments(array $constructorArguments): self
    {
        $this->constructorArguments = $constructorArguments;

        return $this;
    }

    public function setGenericTypes(array $genericTypes): self
    {
        $this->genericTypes = $genericTypes;

        return $this;
    }

    public function resolved(): string
    {
        return Util::resolveClass($this->value);
    }

    public function id(): string
    {
        return $this->resolved();
    }
}
