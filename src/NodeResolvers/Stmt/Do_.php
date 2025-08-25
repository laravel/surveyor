<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Do_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Do_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
