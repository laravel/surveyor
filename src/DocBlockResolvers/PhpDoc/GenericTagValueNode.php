<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class GenericTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\GenericTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
