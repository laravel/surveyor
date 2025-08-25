<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\ComplexType;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ComplexType extends AbstractResolver
{
    public function resolve(Node\ComplexType $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
