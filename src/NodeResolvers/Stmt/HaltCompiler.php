<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class HaltCompiler extends AbstractResolver
{
    public function resolve(Node\Stmt\HaltCompiler $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
