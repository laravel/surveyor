<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\AssignOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class BitwiseAnd extends AbstractResolver
{
    public function resolve(Node\Expr\AssignOp\BitwiseAnd $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
