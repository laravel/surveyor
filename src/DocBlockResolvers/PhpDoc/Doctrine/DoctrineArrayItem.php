<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc\Doctrine;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class DoctrineArrayItem extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\Doctrine\DoctrineArrayItem $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
