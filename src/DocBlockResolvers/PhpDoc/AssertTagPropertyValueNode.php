<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class AssertTagPropertyValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\AssertTagPropertyValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
