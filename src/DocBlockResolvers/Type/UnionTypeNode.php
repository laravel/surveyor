<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class UnionTypeNode extends AbstractResolver
{
    public function resolve(Ast\Type\UnionTypeNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
