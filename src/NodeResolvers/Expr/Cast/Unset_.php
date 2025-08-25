<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\Cast;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Unset_ extends AbstractResolver
{
    public function resolve(Node\Expr\Cast\Unset_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
