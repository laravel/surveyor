<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Trait_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Trait_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
