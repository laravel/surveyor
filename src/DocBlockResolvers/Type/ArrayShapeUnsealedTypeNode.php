<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ArrayShapeUnsealedTypeNode extends AbstractResolver
{
    public function resolve(Ast\Type\ArrayShapeUnsealedTypeNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
