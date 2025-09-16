<?php

namespace Laravel\Surveyor\NodeResolvers\Scalar\MagicConst;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Trait_ extends AbstractResolver
{
    public function resolve(Node\Scalar\MagicConst\Trait_ $node)
    {
        return Type::string($this->scope->entityName());
    }
}
