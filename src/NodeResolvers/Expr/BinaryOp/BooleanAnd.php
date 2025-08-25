<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class BooleanAnd extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\BooleanAnd $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
