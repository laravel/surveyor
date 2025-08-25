<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Attribute;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class Attribute extends AbstractResolver
{
    public function resolve(Ast\Attribute $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
