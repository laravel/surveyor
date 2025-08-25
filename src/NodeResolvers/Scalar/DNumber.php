<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Scalar;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class DNumber extends AbstractResolver
{
    public function resolve(Node\Scalar\DNumber $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
