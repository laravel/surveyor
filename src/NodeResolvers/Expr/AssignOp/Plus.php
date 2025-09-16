<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\AssignOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Plus extends AbstractResolver
{
    public function resolve(Node\Expr\AssignOp\Plus $node)
    {
        return null;
    }
}
