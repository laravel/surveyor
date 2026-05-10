<?php

namespace Laravel\Surveyor\Types;

abstract class AbstractType implements Contracts\Type
{
    public bool $nullable = false;

    public bool $required = true;

    abstract public function id(): string;

    public function nullable(bool $nullable = true): static
    {
        if ($this->nullable === $nullable) {
            return $this;
        }

        $clone = clone $this;
        $clone->nullable = $nullable;

        return $clone;
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

        $clone = clone $this;
        $clone->required = $required;

        return $clone;
    }

    public function optional(bool $optional = true): static
    {
        $required = ! $optional;

        if ($this->required === $required) {
            return $this;
        }

        $clone = clone $this;
        $clone->required = $required;

        return $clone;
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
