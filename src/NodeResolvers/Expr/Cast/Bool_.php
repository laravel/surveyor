<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\Cast;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Bool_ extends AbstractResolver
{
    public function resolve(Node\Expr\Cast\Bool_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
