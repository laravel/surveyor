<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ClosureUse extends AbstractResolver
{
    public function resolve(Node\Expr\ClosureUse $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
