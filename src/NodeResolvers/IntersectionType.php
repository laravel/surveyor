<?php

namespace Laravel\Surveyor\NodeResolvers;

use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class IntersectionType extends AbstractResolver
{
    public function resolve(Node\IntersectionType $node)
    {
        return Type::intersection(
            ...array_map(
                fn ($type) => $this->from($type),
                $node->types,
            ),
        );
    }
}
