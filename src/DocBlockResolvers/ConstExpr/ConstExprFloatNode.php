<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\ConstExpr;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ConstExprFloatNode extends AbstractResolver
{
    public function resolve(Ast\ConstExpr\ConstExprFloatNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
