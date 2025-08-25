<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Plus extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Plus $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
