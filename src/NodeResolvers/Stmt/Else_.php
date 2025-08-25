<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Else_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Else_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
