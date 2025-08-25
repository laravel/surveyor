<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\ConstExpr;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ConstExprIntegerNode extends AbstractResolver
{
    public function resolve(Ast\ConstExpr\ConstExprIntegerNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
