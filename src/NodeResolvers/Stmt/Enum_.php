<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Enum_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Enum_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
