<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Error extends AbstractResolver
{
    public function resolve(Node\Expr\Error $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
