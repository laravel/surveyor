<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class AssertTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\AssertTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
