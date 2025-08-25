<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class UnaryPlus extends AbstractResolver
{
    public function resolve(Node\Expr\UnaryPlus $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
