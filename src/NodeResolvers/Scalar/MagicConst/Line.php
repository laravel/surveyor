<?php

namespace Laravel\Surveyor\NodeResolvers\Scalar\MagicConst;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Line extends AbstractResolver
{
    public function resolve(Node\Scalar\MagicConst\Line $node)
    {
        return Type::int();
    }
}
