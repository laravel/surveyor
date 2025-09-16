<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\BinaryOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Greater extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Greater $node)
    {
        return null;
    }

    public function resolveForCondition(Node\Expr\BinaryOp\Greater $node)
    {
        return null;
    }
}
