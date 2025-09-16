<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\Cast;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Object_ extends AbstractResolver
{
    public function resolve(Node\Expr\Cast\Object_ $node)
    {
        return Type::object();
    }
}
