<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ParamImmediatelyInvokedCallableTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\ParamImmediatelyInvokedCallableTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
