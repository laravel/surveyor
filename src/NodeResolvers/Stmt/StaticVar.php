<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class StaticVar extends AbstractResolver
{
    public function resolve(Node\Stmt\StaticVar $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
