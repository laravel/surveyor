<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Static_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Static_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
