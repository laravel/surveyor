<?php

namespace Laravel\Surveyor\Types;

class ArrayType extends AbstractType implements Contracts\Type
{
    /**
     * An empty value means either "contents unknown" (a declared `array`) or
     * "really empty" (a literal `[]`). Only the first should give way to a
     * keyed branch; dropping the second makes keys that a branch leaves out
     * look as if they were always there.
     */
    protected bool $knownEmpty = false;

    public function __construct(public readonly array $value)
    {
        //
    }

    public function asKnownEmpty(): static
    {
        $copy = clone $this;
        $copy->knownEmpty = true;

        return $copy;
    }

    public function isKnownEmpty(): bool
    {
        return $this->knownEmpty && $this->value === [];
    }

    public function keys(): array
    {
        return array_keys($this->value);
    }

    public function keyType(): Contracts\Type
    {
        $types = array_keys($this->value);

        if (count($types) === 0) {
            return Type::union(Type::string(), Type::int());
        }

        return Type::union(...array_map(fn ($type) => Type::from($type), $types));
    }

    public function valueType(): Contracts\Type
    {
        $types = array_values($this->value);

        if (count($types) === 0) {
            return Type::mixed();
        }

        return Type::union(...$types);
    }

    public function isMoreSpecificThan(Contracts\Type $type): bool
    {
        if ($type instanceof ArrayShapeType && $this->value !== []) {
            return true;
        }

        // A keyed branch or a literal [] replaces an array of unknown contents, such
        // as a declared `array` return. Nothing replaces a literal [], since it says
        // the keys of the other branches may be missing.
        if ($type instanceof ArrayType && $type->value === []) {
            return ! $type->isKnownEmpty() && ($this->value !== [] || $this->isKnownEmpty());
        }

        return false;
    }

    public function isList(): bool
    {
        return array_is_list($this->value);
    }

    public function id(): string
    {
        return $this->isKnownEmpty() ? 'empty' : json_encode($this->value);
    }
}
