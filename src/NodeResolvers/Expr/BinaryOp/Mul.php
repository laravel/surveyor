<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Mul extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Mul $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
