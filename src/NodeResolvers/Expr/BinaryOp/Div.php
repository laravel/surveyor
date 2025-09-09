<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class Div extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Div $node)
    {
        return Type::number();
    }
}
