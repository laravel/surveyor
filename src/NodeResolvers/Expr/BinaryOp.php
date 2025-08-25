<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class BinaryOp extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
