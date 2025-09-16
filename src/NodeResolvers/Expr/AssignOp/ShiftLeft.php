<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\AssignOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ShiftLeft extends AbstractResolver
{
    public function resolve(Node\Expr\AssignOp\ShiftLeft $node)
    {
        return null;
    }
}
