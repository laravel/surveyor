<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Identifier;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Identifier extends AbstractResolver
{
    public function resolve(Node\Identifier $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
