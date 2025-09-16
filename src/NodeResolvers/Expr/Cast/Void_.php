<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\Cast;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Void_ extends AbstractResolver
{
    public function resolve(Node\Expr\Cast\Void_ $node)
    {
        return Type::void();
    }
}
