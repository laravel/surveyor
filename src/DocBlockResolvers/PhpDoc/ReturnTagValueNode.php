<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\PhpDoc;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ReturnTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\ReturnTagValueNode $node)
    {
        return $this->from($node->type);
    }
}
