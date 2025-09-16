<?php

namespace Laravel\Surveyor\NodeResolvers\Scalar\MagicConst;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Dir extends AbstractResolver
{
    public function resolve(Node\Scalar\MagicConst\Dir $node)
    {
        return Type::string();
    }
}
