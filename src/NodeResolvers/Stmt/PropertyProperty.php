<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class PropertyProperty extends AbstractResolver
{
    public function resolve(Node\Stmt\PropertyProperty $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
