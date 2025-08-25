<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Expression extends AbstractResolver
{
    public function resolve(Node\Stmt\Expression $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
