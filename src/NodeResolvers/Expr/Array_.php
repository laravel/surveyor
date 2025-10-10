<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Illuminate\Support\Arr;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Array_ extends AbstractResolver
{
    public function resolve(Node\Expr\Array_ $node)
    {
        $isList = Arr::first($node->items, fn ($item) => $item->key !== null);

        if ($isList) {
            return Type::array(
                array_values(
                    array_unique(
                        array_map(fn ($item) => $this->from($item->value), $node->items)
                    ),
                ),
            );
        }

        return Type::array(
            collect($node->items)
                ->mapWithKeys(fn ($item) => [
                    $item->key->value ?? null => $this->from($item->value),
                ])
                ->values()
                ->toArray(),
        );
    }
}
