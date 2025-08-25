<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Pipe extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Pipe $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
