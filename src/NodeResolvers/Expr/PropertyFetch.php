<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class PropertyFetch extends AbstractResolver
{
    public function resolve(Node\Expr\PropertyFetch $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
