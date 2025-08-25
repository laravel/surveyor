<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc\Doctrine;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class DoctrineAnnotation extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\Doctrine\DoctrineAnnotation $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
