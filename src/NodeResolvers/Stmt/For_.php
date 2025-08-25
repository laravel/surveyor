<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class For_ extends AbstractResolver
{
    public function resolve(Node\Stmt\For_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
