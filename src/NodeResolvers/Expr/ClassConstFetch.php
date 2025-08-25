<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ClassConstFetch extends AbstractResolver
{
    public function resolve(Node\Expr\ClassConstFetch $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
