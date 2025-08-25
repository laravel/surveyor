<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\AssignOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Mul extends AbstractResolver
{
    public function resolve(Node\Expr\AssignOp\Mul $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
