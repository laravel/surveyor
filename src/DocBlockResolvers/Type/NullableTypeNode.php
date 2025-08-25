<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class NullableTypeNode extends AbstractResolver
{
    public function resolve(Ast\Type\NullableTypeNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
