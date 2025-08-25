<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class MethodTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\MethodTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
