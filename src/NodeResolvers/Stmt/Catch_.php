<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Catch_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Catch_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
