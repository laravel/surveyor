<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Scalar;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class String_ extends AbstractResolver
{
    public function resolve(Node\Scalar\String_ $node)
    {
        return Type::string($node->value);
    }
}
