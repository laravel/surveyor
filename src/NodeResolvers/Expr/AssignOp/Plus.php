<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\AssignOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Plus extends AbstractResolver
{
    public function resolve(Node\Expr\AssignOp\Plus $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
