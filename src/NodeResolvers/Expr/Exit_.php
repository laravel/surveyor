<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Exit_ extends AbstractResolver
{
    public function resolve(Node\Expr\Exit_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
