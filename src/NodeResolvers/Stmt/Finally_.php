<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Finally_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Finally_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
