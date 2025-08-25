<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Goto_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Goto_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
