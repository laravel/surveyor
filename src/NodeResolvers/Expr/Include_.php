<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Include_ extends AbstractResolver
{
    public function resolve(Node\Expr\Include_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
