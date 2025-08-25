<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class CallableTypeNode extends AbstractResolver
{
    public function resolve(Ast\Type\CallableTypeNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
