<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Scalar;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Scalar extends AbstractResolver
{
    public function resolve(Node\Scalar $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
