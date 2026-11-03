<?php

namespace Laravel\Surveyor\Types;

abstract class AbstractType implements Contracts\Type
{
    public bool $nullable = false;

    public bool $required = true;

    abstract public function id(): string;

    /**
     * Types are shared between results, so a change returns a copy instead of
     * altering every other place that holds the same instance.
     */
    public function nullable(bool $nullable = true): static
    {
        if ($this->nullable === $nullable) {
            return $this;
        }

        $copy = clone $this;
        $copy->nullable = $nullable;

        return $copy;
    }

    public function isMoreSpecificThan(Contracts\Type $type): bool
    {
        return false;
    }

    public function required(bool $required = true): static
    {
        if ($this->required === $required) {
            return $this;
        }

        $copy = clone $this;
        $copy->required = $required;

        return $copy;
    }

    public function optional(bool $optional = true): static
    {
        return $this->required(! $optional);
    }

    public function isOptional(): bool
    {
        return ! $this->required;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function toString(): string
    {
        return static::class.':'.$this->id();
    }
}
