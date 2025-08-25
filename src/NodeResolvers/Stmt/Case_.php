<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Case_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Case_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
