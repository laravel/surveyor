<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ArrayShapeItemNode extends AbstractResolver
{
    public function resolve(Ast\Type\ArrayShapeItemNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
