<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\NodeVisitor;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class CloningVisitor extends AbstractResolver
{
    public function resolve(Ast\NodeVisitor\CloningVisitor $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
