<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class RequireExtendsTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\RequireExtendsTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
