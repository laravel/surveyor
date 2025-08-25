<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\ArrayItem;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ArrayItem extends AbstractResolver
{
    public function resolve(Node\ArrayItem $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
