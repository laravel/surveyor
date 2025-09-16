<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\AssignOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Pow extends AbstractResolver
{
    public function resolve(Node\Expr\AssignOp\Pow $node)
    {
        return null;
    }
}
