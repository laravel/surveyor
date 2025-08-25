<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class InvalidTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\InvalidTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
