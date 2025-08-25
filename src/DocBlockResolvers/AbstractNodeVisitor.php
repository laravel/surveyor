<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\AbstractNodeVisitor;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class AbstractNodeVisitor extends AbstractResolver
{
    public function resolve(Ast\AbstractNodeVisitor $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
