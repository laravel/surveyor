<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc\Doctrine;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class DoctrineArgument extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\Doctrine\DoctrineArgument $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
