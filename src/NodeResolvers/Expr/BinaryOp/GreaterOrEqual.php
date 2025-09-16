<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\BinaryOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class GreaterOrEqual extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\GreaterOrEqual $node)
    {
        return null;
    }
}
