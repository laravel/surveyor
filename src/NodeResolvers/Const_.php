<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Const_;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Const_ extends AbstractResolver
{
    public function resolve(Node\Const_ $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
