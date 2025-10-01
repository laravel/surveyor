<?php

namespace Laravel\Surveyor\Types;

use Illuminate\Support\Collection;

class UnionType extends AbstractType implements Contracts\CollapsibleType, Contracts\MultiType, Contracts\Type
{
    public function __construct(public readonly array $types = [])
    {
        //
    }

    public function collapse(): Contracts\Type
    {
        return Type::union(
            ...collect($this->types)
                ->groupBy(fn ($type) => $type::class)
                ->map(fn ($group, $class) => $this->collapseType($group, $class))
                ->values()
                ->flatten()
                ->all()
        );
    }

    protected function collapseType(Collection $types, string $class)
    {
        return match ($class) {
            ArrayType::class => $this->collapseArrayType($types),
            default => Type::union(...$types->all()),
        };
    }

    protected function collapseArrayType(Collection $types)
    {
        $dataKeys = $types->map(fn ($type) => array_keys($type->value));
        $requiredKeys = array_values(array_intersect(...$dataKeys->all()));

        $newData = [];

        foreach ($types as $type) {
            foreach ($type->value as $key => $value) {
                $value->required(in_array($key, $requiredKeys));

                $newData[$key] ??= [];
                $newData[$key][] = $value;
            }
        }

        foreach ($newData as $key => $value) {
            $newData[$key] = Type::union(...$value);
        }

        return Type::array($newData);
    }

    public function id(): string
    {
        return collect($this->types)->toJson();
    }
}
