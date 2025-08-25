<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class MethodTagValueParameterNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\MethodTagValueParameterNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
