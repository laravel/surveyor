<?php

namespace Laravel\Surveyor\DocBlockResolvers\PhpDoc;

use Laravel\Surveyor\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class ParamOutTagValueNode extends AbstractResolver
{
    public function resolve(Ast\PhpDoc\ParamOutTagValueNode $node)
    {
        return null;
    }
}
