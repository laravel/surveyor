<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\Cast;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Array_ extends AbstractResolver
{
    public function resolve(Node\Expr\Cast\Array_ $node)
    {
        return Type::array([]);
    }
}
