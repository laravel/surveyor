<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Clone_ extends AbstractResolver
{
    public function resolve(Node\Expr\Clone_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
