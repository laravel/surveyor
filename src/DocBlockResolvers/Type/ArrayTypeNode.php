<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ArrayTypeNode extends AbstractResolver
{
    public function resolve(Ast\Type\ArrayTypeNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
