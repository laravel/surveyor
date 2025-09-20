<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Case_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Case_ $node)
    {
        return null;
    }
}
