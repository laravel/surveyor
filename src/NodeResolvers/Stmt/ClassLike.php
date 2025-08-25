<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ClassLike extends AbstractResolver
{
    public function resolve(Node\Stmt\ClassLike $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
