<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class PostDec extends AbstractResolver
{
    public function resolve(Node\Expr\PostDec $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
