<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class PureUnlessCallableIsImpureTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\PureUnlessCallableIsImpureTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
