<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\BinaryOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class LogicalXor extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\LogicalXor $node)
    {
        return Type::bool();
    }
}
