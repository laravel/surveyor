<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Arg;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Arg extends AbstractResolver
{
    public function resolve(Node\Arg $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
