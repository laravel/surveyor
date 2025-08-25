<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Foreach_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Foreach_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
