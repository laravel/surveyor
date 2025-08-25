<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Match_ extends AbstractResolver
{
    public function resolve(Node\Expr\Match_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
