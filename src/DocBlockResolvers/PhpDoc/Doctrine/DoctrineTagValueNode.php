<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc\Doctrine;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class DoctrineTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\Doctrine\DoctrineTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
