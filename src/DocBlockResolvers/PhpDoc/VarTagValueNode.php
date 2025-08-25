<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class VarTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\VarTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
