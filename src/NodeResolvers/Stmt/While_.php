<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class While_ extends AbstractResolver
{
    public function resolve(Node\Stmt\While_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
