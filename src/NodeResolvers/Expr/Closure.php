<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Closure extends AbstractResolver
{
    public function resolve(Node\Expr\Closure $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
