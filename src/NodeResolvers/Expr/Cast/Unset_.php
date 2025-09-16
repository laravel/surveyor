<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\Cast;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Unset_ extends AbstractResolver
{
    public function resolve(Node\Expr\Cast\Unset_ $node)
    {
        return Type::null();
    }
}
