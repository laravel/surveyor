<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\BinaryOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class ShiftRight extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\ShiftRight $node)
    {
        return Type::number();
    }
}
