<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\ConstExpr;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ConstExprStringNode extends AbstractResolver
{
    public function resolve(Ast\ConstExpr\ConstExprStringNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
