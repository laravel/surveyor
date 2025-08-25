<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class NullsafeMethodCall extends AbstractResolver
{
    public function resolve(Node\Expr\NullsafeMethodCall $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
