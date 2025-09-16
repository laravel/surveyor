<?php

namespace Laravel\Surveyor\NodeResolvers\Scalar\MagicConst;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Class_ extends AbstractResolver
{
    public function resolve(Node\Scalar\MagicConst\Class_ $node)
    {
        return Type::from($this->scope->entityName());
    }
}
