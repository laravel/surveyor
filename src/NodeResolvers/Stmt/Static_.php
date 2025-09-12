<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Static_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Static_ $node)
    {
        return null;
    }
}
