<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Scalar;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Encapsed extends AbstractResolver
{
    public function resolve(Node\Scalar\Encapsed $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
