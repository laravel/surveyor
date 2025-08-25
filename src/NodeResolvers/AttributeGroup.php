<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\AttributeGroup;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class AttributeGroup extends AbstractResolver
{
    public function resolve(Node\AttributeGroup $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
