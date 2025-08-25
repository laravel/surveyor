<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\DeclareItem;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class DeclareItem extends AbstractResolver
{
    public function resolve(Node\DeclareItem $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
