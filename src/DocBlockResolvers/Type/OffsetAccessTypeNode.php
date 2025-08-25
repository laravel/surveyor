<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class OffsetAccessTypeNode extends AbstractResolver
{
    public function resolve(Ast\Type\OffsetAccessTypeNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
