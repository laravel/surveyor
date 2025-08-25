<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Attribute;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Attribute extends AbstractResolver
{
    public function resolve(Node\Attribute $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
