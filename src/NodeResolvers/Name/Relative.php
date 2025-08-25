<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Name;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Relative extends AbstractResolver
{
    public function resolve(Node\Name\Relative $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
