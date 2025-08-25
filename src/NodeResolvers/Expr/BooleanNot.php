<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class BooleanNot extends AbstractResolver
{
    public function resolve(Node\Expr\BooleanNot $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
