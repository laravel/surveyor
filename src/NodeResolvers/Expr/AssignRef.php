<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class AssignRef extends AbstractResolver
{
    public function resolve(Node\Expr\AssignRef $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
