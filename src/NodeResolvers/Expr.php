<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Expr extends AbstractResolver
{
    public function resolve(Node\Expr $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
