<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Scalar\MagicConst;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Function_ extends AbstractResolver
{
    public function resolve(Node\Scalar\MagicConst\Function_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
