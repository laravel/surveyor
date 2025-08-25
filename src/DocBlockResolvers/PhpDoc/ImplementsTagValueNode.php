<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ImplementsTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\ImplementsTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
