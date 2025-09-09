<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\Cast;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class Int_ extends AbstractResolver
{
    public function resolve(Node\Expr\Cast\Int_ $node)
    {
        return Type::int();
    }
}
