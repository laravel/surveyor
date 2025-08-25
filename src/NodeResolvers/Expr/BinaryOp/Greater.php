<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Greater extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Greater $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
