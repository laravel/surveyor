<?php

namespace Laravel\Surveyor\NodeResolvers;

use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class PropertyItem extends AbstractResolver
{
    public function resolve(Node\PropertyItem $node)
    {
        $this->scope->state()->add(
            $node,
            $node->default ? $this->from($node->default) : Type::null(),
        );

        return null;
    }
}
