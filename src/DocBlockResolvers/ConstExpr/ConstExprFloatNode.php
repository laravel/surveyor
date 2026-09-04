<?php

namespace Laravel\Surveyor\DocBlockResolvers\ConstExpr;

use Laravel\Surveyor\DocBlockResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PHPStan\PhpDocParser\Ast;

class ConstExprFloatNode extends AbstractResolver
{
    public function resolve(Ast\ConstExpr\ConstExprFloatNode $node)
    {
        return Type::float((float) str_replace('_', '', $node->value));
    }
}
