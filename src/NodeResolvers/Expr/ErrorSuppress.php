<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ErrorSuppress extends AbstractResolver
{
    public function resolve(Node\Expr\ErrorSuppress $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
