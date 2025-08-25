<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Cast extends AbstractResolver
{
    public function resolve(Node\Expr\Cast $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
