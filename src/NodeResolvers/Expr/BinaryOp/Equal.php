<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Equal extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Equal $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
