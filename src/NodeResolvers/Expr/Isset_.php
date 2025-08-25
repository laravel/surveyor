<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Isset_ extends AbstractResolver
{
    public function resolve(Node\Expr\Isset_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
