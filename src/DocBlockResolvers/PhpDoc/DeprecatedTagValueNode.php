<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class DeprecatedTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\DeprecatedTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
