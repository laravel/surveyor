<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ConstTypeNode extends AbstractResolver
{
    public function resolve(Ast\Type\ConstTypeNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
