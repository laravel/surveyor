<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class TypelessParamTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\TypelessParamTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
