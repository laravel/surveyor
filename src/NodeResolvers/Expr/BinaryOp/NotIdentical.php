<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class NotIdentical extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\NotIdentical $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
