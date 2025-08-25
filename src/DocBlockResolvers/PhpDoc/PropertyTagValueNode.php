<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class PropertyTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\PropertyTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
