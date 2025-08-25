<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\UseItem;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class UseItem extends AbstractResolver
{
    public function resolve(Node\UseItem $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
