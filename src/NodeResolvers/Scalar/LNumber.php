<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Scalar;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class LNumber extends AbstractResolver
{
    public function resolve(Node\Scalar\LNumber $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
