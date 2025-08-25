<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Continue_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Continue_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
