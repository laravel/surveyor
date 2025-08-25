<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ThrowsTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\ThrowsTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
