<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class GenericTypeNode extends AbstractResolver
{
    public function resolve(Ast\Type\GenericTypeNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
