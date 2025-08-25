<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class TemplateTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\TemplateTagValueNode $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
