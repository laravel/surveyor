<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class New_ extends AbstractResolver
{
    public function resolve(Node\Expr\New_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
