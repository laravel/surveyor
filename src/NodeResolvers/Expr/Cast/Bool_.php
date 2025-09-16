<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\Cast;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Bool_ extends AbstractResolver
{
    public function resolve(Node\Expr\Cast\Bool_ $node)
    {
        return Type::bool();
    }
}
