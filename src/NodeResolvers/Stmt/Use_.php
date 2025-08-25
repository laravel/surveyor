<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Use_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Use_ $node)
    {
        return null;
    }
}
