<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\AssignOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Mod extends AbstractResolver
{
    public function resolve(Node\Expr\AssignOp\Mod $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
