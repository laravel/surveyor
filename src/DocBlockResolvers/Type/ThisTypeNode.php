<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ThisTypeNode extends AbstractResolver
{
    public function resolve(Ast\Type\ThisTypeNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
