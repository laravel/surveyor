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
        $isList = Arr::first($node->items, fn ($item) => $item->key === null) !== null;

        if ($isList) {
            return Type::array(
                array_map(fn ($item) => $this->from($item->value), $node->items),
            );
        }

        $result = [];

        foreach ($node->items as $item) {
            $result[$item->key->value ?? null] = $this->from($item->value);
        }

        return Type::array($result);
    }
}
