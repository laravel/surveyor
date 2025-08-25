<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class SelfOutTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\SelfOutTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
