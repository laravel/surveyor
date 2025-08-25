<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class GreaterOrEqual extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\GreaterOrEqual $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
