<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\IntersectionType;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class IntersectionType extends AbstractResolver
{
    public function resolve(Node\IntersectionType $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
