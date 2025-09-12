<?php

namespace Laravel\Surveyor\DocBlockResolvers\Type;

use Laravel\Surveyor\DocBlockResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PHPStan\PhpDocParser\Ast;

class ThisTypeNode extends AbstractResolver
{
    public function resolve(Ast\Type\ThisTypeNode $node)
    {
        return Type::from($this->scope->entityName());
    }
}
