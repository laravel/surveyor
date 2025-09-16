<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\AssignOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Minus extends AbstractResolver
{
    public function resolve(Node\Expr\AssignOp\Minus $node)
    {
        return null;
    }
}
