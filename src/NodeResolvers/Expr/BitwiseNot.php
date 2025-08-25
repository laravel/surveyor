<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class BitwiseNot extends AbstractResolver
{
    public function resolve(Node\Expr\BitwiseNot $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
