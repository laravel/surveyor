<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class DeclareDeclare extends AbstractResolver
{
    public function resolve(Node\Stmt\DeclareDeclare $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
