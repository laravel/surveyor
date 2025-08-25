<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Yield_ extends AbstractResolver
{
    public function resolve(Node\Expr\Yield_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
