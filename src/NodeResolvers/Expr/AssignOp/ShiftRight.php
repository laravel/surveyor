<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\AssignOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ShiftRight extends AbstractResolver
{
    public function resolve(Node\Expr\AssignOp\ShiftRight $node)
    {
        return null;
    }
}
