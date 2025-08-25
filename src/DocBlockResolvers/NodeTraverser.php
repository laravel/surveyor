<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\NodeTraverser;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class NodeTraverser extends AbstractResolver
{
    public function resolve(Ast\NodeTraverser $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
