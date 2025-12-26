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
        $id = $this->resolved();

        if (! empty($this->genericTypes)) {
            $genericIds = array_map(
                fn ($type) => $type->id(),
                $this->genericTypes
            );
            $id .= '<'.implode(',', $genericIds).'>';
        }

        return $id;
    }

    public function genericTypes(): array
    {
        return $this->genericTypes;
    }

    public function isMoreSpecificThan(Contracts\Type $type): bool
    {
        if (! $type instanceof ClassType) {
            return false;
        }

        if ($this->resolved() !== $type->resolved()) {
            return false;
        }

        return ! empty($this->genericTypes) && empty($type->genericTypes());
    }
}
