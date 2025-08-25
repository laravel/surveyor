<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class NotEqual extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\NotEqual $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
