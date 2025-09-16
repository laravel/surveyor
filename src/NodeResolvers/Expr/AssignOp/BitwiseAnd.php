<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\AssignOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class BitwiseAnd extends AbstractResolver
{
    public function resolve(Node\Expr\AssignOp\BitwiseAnd $node)
    {
        return null;
    }
}
