<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ArrayShapeNode extends AbstractResolver
{
    public function resolve(Ast\Type\ArrayShapeNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
