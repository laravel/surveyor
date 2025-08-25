<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Nop extends AbstractResolver
{
    public function resolve(Node\Stmt\Nop $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
