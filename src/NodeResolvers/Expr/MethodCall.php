<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class MethodCall extends AbstractResolver
{
    public function resolve(Node\Expr\MethodCall $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
