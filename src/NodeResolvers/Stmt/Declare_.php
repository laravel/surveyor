<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Declare_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Declare_ $node)
    {
        return null;
    }
}
