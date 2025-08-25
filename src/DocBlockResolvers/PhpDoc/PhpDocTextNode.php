<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class PhpDocTextNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\PhpDocTextNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
