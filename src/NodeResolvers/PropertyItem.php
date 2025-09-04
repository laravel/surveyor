<?php

namespace Laravel\StaticAnalyzer\NodeResolvers;

use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class PropertyItem extends AbstractResolver
{
    public function resolve(Node\PropertyItem $node)
    {
        $this->scope->properties()->add(
            $node->name->name,
            $node->default ? $this->from($node->default) : Type::null(),
            $node->getStartLine()
        );

        return null;
    }
}
