<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\AssignOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Pow extends AbstractResolver
{
    public function resolve(Node\Expr\AssignOp\Pow $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
