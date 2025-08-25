<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class LogicalXor extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\LogicalXor $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
