<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\AssignOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Coalesce extends AbstractResolver
{
    public function resolve(Node\Expr\AssignOp\Coalesce $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
