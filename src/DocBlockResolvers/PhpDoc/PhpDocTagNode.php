<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class PhpDocTagNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\PhpDocTagNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
