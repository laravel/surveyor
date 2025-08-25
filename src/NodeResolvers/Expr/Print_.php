<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Print_ extends AbstractResolver
{
    public function resolve(Node\Expr\Print_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
