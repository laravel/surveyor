<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ObjectShapeNode extends AbstractResolver
{
    public function resolve(Ast\Type\ObjectShapeNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
