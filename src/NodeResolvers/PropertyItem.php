<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\PropertyItem;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class PropertyItem extends AbstractResolver
{
    public function resolve(Node\PropertyItem $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
