<?php

namespace Laravel\Surveyor\NodeResolvers\Scalar\MagicConst;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class File extends AbstractResolver
{
    public function resolve(Node\Scalar\MagicConst\File $node)
    {
        return Type::string();
    }
}
