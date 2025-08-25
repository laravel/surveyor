<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Instanceof_ extends AbstractResolver
{
    public function resolve(Node\Expr\Instanceof_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
