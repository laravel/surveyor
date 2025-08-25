<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\AssignOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class BitwiseOr extends AbstractResolver
{
    public function resolve(Node\Expr\AssignOp\BitwiseOr $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
