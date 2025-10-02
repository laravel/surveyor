<?php

namespace Laravel\Surveyor\NodeResolvers;

use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class VarLikeIdentifier extends AbstractResolver
{
    public function resolve(Node\VarLikeIdentifier $node)
    {
        return Type::from($node->name);
    }
}
