<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\AssignOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class BitwiseOr extends AbstractResolver
{
    public function resolve(Node\Expr\AssignOp\BitwiseOr $node)
    {
        return null;
    }
}
