<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ParamClosureThisTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\ParamClosureThisTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
