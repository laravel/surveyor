<?php

namespace Laravel\Surveyor\DocBlockResolvers\ConstExpr;

use Laravel\Surveyor\DocBlockResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PHPStan\PhpDocParser\Ast;

class ConstExprIntegerNode extends AbstractResolver
{
    public function resolve(Ast\ConstExpr\ConstExprIntegerNode $node)
    {
        // phpdoc-parser hands back the literal verbatim, so hex, binary, octal and
        // underscore-separated forms arrive as strings PHP will not coerce to int.
        return Type::int(intval(str_replace('_', '', $node->value), 0));
    }
}
