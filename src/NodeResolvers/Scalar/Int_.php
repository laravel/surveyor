<?php

namespace Laravel\Surveyor\NodeResolvers\Scalar;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Int_ extends AbstractResolver
{
    public function resolve(Node\Scalar\Int_ $node)
    {
        return Type::int($node->value);
    }

    public function resolveForCondition(Node\Scalar\Int_ $node)
    {
        return $this->resolve($node);
    }
}
