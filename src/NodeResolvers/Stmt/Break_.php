<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Break_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Break_ $node)
    {
        return null;
    }
}
