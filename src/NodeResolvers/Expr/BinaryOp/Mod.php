<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Mod extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Mod $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
