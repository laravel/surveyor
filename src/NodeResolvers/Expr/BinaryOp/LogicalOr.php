<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class LogicalOr extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\LogicalOr $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
