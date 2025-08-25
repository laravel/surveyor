<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class SealedTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\SealedTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
