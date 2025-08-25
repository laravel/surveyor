<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ObjectShapeItemNode extends AbstractResolver
{
    public function resolve(Ast\Type\ObjectShapeItemNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
