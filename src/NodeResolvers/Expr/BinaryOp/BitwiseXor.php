<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\BinaryOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class BitwiseXor extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\BitwiseXor $node)
    {
        return null;
    }
}
