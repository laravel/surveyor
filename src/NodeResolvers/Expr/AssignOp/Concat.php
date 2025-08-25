<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\AssignOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Concat extends AbstractResolver
{
    public function resolve(Node\Expr\AssignOp\Concat $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
