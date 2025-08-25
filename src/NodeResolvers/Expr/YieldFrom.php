<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class YieldFrom extends AbstractResolver
{
    public function resolve(Node\Expr\YieldFrom $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
