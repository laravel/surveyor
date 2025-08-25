<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class UseUse extends AbstractResolver
{
    public function resolve(Node\Stmt\UseUse $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
