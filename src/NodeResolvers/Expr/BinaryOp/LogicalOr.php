<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\BinaryOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class LogicalOr extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\LogicalOr $node)
    {
        return Type::bool();
    }
}
