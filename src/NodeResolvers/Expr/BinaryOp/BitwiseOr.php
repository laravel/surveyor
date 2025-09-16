<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\BinaryOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class BitwiseOr extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\BitwiseOr $node)
    {
        return null;
    }
}
