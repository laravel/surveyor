<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ConstFetch extends AbstractResolver
{
    public function resolve(Node\Expr\ConstFetch $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
