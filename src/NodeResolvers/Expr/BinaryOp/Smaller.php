<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Smaller extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Smaller $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
