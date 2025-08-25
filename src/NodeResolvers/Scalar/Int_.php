<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Scalar;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class Int_ extends AbstractResolver
{
    public function resolve(Node\Scalar\Int_ $node)
    {
        return Type::int($node->value);
    }
}
