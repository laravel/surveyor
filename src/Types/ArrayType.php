<?php

namespace Laravel\StaticAnalyzer\Types;

use Illuminate\Support\Collection;

class ArrayType extends AbstractType implements Contracts\Type
{
    public function __construct(public readonly array|Collection $value)
    {
        //
    }

    /**
     * @return Collection<int|string, mixed>
     */
    public function keys()
    {
        return collect($this->value)->keys();
    }

    public function isList(): bool
    {
        return array_is_list(collect($this->value)->toArray());
    }

    public function id(): string
    {
        return collect($this->value)->toJson();
    }
}
