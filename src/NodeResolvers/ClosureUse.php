<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\ClosureUse;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ClosureUse extends AbstractResolver
{
    public function resolve(Node\ClosureUse $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
