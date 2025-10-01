<?php

namespace Laravel\Surveyor\NodeResolvers;

use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class StaticVar extends AbstractResolver
{
    public function resolve(Node\StaticVar $node)
    {
        $type = $node->default ? $this->from($node->default) : Type::mixed();

        $this->scope->state()->add($node, $type);

        return $type;
    }
}
