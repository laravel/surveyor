<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\AssignOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class BitwiseXor extends AbstractResolver
{
    public function resolve(Node\Expr\AssignOp\BitwiseXor $node)
    {
        return null;
    }
}
