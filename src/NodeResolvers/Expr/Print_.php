<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Print_ extends AbstractResolver
{
    public function resolve(Node\Expr\Print_ $node)
    {
        return null;
    }
}
