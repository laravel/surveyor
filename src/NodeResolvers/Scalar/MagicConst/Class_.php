<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Scalar\MagicConst;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Class_ extends AbstractResolver
{
    public function resolve(Node\Scalar\MagicConst\Class_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
