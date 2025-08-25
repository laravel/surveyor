<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class TypeAliasImportTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\TypeAliasImportTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
