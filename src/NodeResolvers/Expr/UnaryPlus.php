<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class UnaryPlus extends AbstractResolver
{
    public function resolve(Node\Expr\UnaryPlus $node)
    {
        return Type::number();
    }
}
