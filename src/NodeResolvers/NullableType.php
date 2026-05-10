<?php

namespace Laravel\Surveyor\NodeResolvers;

use PhpParser\Node;

class NullableType extends AbstractResolver
{
    public function resolve(Node\NullableType $node)
    {
        $type = $this->from($node->type);

        return $type->nullable();
    }
}
