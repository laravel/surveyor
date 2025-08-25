<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Const_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Const_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
