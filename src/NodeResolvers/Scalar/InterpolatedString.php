<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Scalar;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class InterpolatedString extends AbstractResolver
{
    public function resolve(Node\Scalar\InterpolatedString $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
