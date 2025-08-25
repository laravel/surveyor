<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ParamTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\ParamTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
