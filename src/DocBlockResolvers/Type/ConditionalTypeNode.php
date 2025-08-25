<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ConditionalTypeNode extends AbstractResolver
{
    public function resolve(Ast\Type\ConditionalTypeNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
