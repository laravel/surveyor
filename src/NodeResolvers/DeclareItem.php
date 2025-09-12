<?php

namespace Laravel\Surveyor\NodeResolvers;

use PhpParser\Node;

class DeclareItem extends AbstractResolver
{
    public function resolve(Node\DeclareItem $node)
    {
        return null;
    }
}
