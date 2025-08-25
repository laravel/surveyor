<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Minus extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Minus $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
