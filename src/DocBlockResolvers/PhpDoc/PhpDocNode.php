<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class PhpDocNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\PhpDocNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
