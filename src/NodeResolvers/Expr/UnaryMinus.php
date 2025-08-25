<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class UnaryMinus extends AbstractResolver
{
    public function resolve(Node\Expr\UnaryMinus $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
