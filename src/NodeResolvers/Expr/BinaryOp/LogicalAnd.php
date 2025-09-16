<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\BinaryOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class LogicalAnd extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\LogicalAnd $node)
    {
        return Type::bool();
    }
}
