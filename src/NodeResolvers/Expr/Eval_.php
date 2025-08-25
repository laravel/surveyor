<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Eval_ extends AbstractResolver
{
    public function resolve(Node\Expr\Eval_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
