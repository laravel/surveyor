<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ParamOutTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\ParamOutTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
