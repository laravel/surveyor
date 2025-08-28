<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Throw_ extends AbstractResolver
{
    public function resolve(Node\Expr\Throw_ $node)
    {
        // TODO: Do we need to handle?
        return null;
    }
}
