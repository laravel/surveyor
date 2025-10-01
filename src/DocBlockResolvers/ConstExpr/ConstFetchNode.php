<?php

namespace Laravel\Surveyor\DocBlockResolvers\ConstExpr;

use Laravel\Surveyor\DocBlockResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PHPStan\PhpDocParser\Ast;

class ConstFetchNode extends AbstractResolver
{
    public function resolve(Ast\ConstExpr\ConstFetchNode $node)
    {
        if ($node->className === '') {
            return Type::from($node->name);
        }

        if ($node->name === 'class') {
            return Type::from($node->className);
        }

        if ($node->className === 'self') {
            return $this->scope->getConstant($node->name);
        }

        $className = $this->scope->getUse($node->className);

        return $this->reflector->constantType($node->name, $className);
    }
}
