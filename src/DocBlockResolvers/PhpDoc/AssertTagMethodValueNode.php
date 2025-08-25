<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class AssertTagMethodValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\AssertTagMethodValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
