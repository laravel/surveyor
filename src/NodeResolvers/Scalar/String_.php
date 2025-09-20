<?php

namespace Laravel\Surveyor\NodeResolvers\Scalar;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class String_ extends AbstractResolver
{
    public function resolve(Node\Scalar\String_ $node)
    {
        return Type::string($node->value);
    }

    public function resolveForCondition(Node\Scalar\String_ $node)
    {
        return $this->fromOutsideOfCondition($node);
    }
}
