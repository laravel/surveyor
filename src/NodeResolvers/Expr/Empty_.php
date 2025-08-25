<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Empty_ extends AbstractResolver
{
    public function resolve(Node\Expr\Empty_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
