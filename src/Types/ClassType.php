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

        // Same class: more specific if we have generics and they don't
        if ($this->resolved() === $type->resolved()) {
            return ! empty($this->genericTypes) && empty($type->genericTypes());
        }

        // Different class: more specific if $this is a subtype of $type
        try {
            $selfResolved = $this->resolved();
            $otherResolved = $type->resolved();

            if (
                $selfResolved && $otherResolved
                && (class_exists($selfResolved) || interface_exists($selfResolved))
                && (class_exists($otherResolved) || interface_exists($otherResolved))
            ) {
                return is_a($selfResolved, $otherResolved, true);
            }
        } catch (\Throwable) {
            // ignore reflection errors
        }

        return false;
    }
}
