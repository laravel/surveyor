<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\BinaryOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class NotEqual extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\NotEqual $node)
    {
        return Type::bool();
    }
}
