<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Concat extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Concat $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
