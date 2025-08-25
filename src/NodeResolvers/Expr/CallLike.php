<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class CallLike extends AbstractResolver
{
    public function resolve(Node\Expr\CallLike $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
