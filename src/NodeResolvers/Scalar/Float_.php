<?php

namespace Laravel\Surveyor\NodeResolvers\Scalar;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Float_ extends AbstractResolver
{
    public function resolve(Node\Scalar\Float_ $node)
    {
        return Type::float($node->value);
    }
}
