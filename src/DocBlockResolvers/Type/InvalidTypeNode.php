<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class InvalidTypeNode extends AbstractResolver
{
    public function resolve(Ast\Type\InvalidTypeNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
