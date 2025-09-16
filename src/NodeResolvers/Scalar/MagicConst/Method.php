<?php

namespace Laravel\Surveyor\NodeResolvers\Scalar\MagicConst;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Method extends AbstractResolver
{
    public function resolve(Node\Scalar\MagicConst\Method $node)
    {
        return Type::string($this->scope->methodName());
    }
}
