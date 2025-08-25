<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class IntersectionTypeNode extends AbstractResolver
{
    public function resolve(Ast\Type\IntersectionTypeNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
