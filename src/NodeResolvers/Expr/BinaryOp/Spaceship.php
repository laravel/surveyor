<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Spaceship extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Spaceship $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
