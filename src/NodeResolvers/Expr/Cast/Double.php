<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\Cast;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Double extends AbstractResolver
{
    public function resolve(Node\Expr\Cast\Double $node)
    {
        return Type::float();
    }
}
