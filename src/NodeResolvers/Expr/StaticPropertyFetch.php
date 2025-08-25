<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class StaticPropertyFetch extends AbstractResolver
{
    public function resolve(Node\Expr\StaticPropertyFetch $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
