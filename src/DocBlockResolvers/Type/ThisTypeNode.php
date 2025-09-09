<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
use PHPStan\PhpDocParser\Ast;

class ThisTypeNode extends AbstractResolver
{
    public function resolve(Ast\Type\ThisTypeNode $node)
    {
        return Type::from($this->scope->className());
    }
}
