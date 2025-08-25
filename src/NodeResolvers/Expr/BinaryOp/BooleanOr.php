<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class BooleanOr extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\BooleanOr $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
