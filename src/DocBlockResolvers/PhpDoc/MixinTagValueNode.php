<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class MixinTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\MixinTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
