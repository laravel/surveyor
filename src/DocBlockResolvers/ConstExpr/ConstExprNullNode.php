<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\ConstExpr;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ConstExprNullNode extends AbstractResolver
{
    public function resolve(Ast\ConstExpr\ConstExprNullNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
