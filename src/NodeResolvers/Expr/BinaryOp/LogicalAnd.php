<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class LogicalAnd extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\LogicalAnd $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
