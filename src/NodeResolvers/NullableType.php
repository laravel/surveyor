<?php

namespace Laravel\StaticAnalyzer\NodeResolvers;

use PhpParser\Node;

class NullableType extends AbstractResolver
{
    public function resolve(Node\NullableType $node)
    {
        $type = $this->from($node->type);

        $type->nullable();

        return $type;
    }
}
