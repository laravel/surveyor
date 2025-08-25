<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class RequireImplementsTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\RequireImplementsTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
