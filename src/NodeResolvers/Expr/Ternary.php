<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Ternary extends AbstractResolver
{
    public function resolve(Node\Expr\Ternary $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
