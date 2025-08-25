<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class AssignOp extends AbstractResolver
{
    public function resolve(Node\Expr\AssignOp $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
