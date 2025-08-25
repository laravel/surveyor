<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Assign extends AbstractResolver
{
    public function resolve(Node\Expr\Assign $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
