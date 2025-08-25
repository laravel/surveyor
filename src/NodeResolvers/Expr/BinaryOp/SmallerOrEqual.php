<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class SmallerOrEqual extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\SmallerOrEqual $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
