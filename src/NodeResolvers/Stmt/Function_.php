<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Function_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Function_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
