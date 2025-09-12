<?php

namespace Laravel\Surveyor\NodeResolvers;

use PhpParser\Node;

class StaticVar extends AbstractResolver
{
    public function resolve(Node\StaticVar $node)
    {
        $type = $this->from($node->default);

        $this->scope->variables()->add(
            $node->var->name,
            $type,
            $node,
        );

        return $type;
    }
}
