<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ArrowFunction extends AbstractResolver
{
    public function resolve(Node\Expr\ArrowFunction $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
