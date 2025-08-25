<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\ConstExpr;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ConstFetchNode extends AbstractResolver
{
    public function resolve(Ast\ConstExpr\ConstFetchNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
