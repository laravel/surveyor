<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\BinaryOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Pow extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Pow $node)
    {
        return null;
    }
}
