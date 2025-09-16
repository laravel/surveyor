<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\BinaryOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class BitwiseAnd extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\BitwiseAnd $node)
    {
        return null;
    }
}
