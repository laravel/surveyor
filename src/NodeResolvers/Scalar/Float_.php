<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Scalar;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Float_ extends AbstractResolver
{
    public function resolve(Node\Scalar\Float_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
