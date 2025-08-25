<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Interface_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Interface_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
