<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ClassConst extends AbstractResolver
{
    public function resolve(Node\Stmt\ClassConst $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
