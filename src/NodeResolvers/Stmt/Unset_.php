<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Unset_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Unset_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
