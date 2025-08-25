<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Identical extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Identical $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
