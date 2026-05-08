<?php

namespace Laravel\Surveyor\Types;

class ArrayType extends AbstractType implements Contracts\Type
{
    private ?string $cachedId = null;

    public function __construct(public readonly array $value)
    {
        //
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

        if ($type instanceof ArrayType && $type->value === [] && $this->value !== []) {
            return true;
        }

        return false;
    }

    public function isList(): bool
    {
        return array_is_list($this->value);
    }

    public function id(): string
    {
        return $this->cachedId ??= $this->determineId();
    }

    protected function determineId(): string
    {
        $parts = [];

        foreach ($this->value as $key => $type) {
            $parts[] = $key.'=>'.(
                $type instanceof Contracts\Type ? $type->id()
                : (is_scalar($type)
                    ? (string) $type : 'mixed')
            );
        }

        return '['.implode(',', $parts).']';
    }
}
