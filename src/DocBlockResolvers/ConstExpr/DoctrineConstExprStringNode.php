<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\ConstExpr;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class DoctrineConstExprStringNode extends AbstractResolver
{
    public function resolve(Ast\ConstExpr\DoctrineConstExprStringNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
