<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Div extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Div $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
