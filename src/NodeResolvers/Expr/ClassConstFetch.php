<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ClassConstFetch extends AbstractResolver
{
    public function resolve(Node\Expr\ClassConstFetch $node)
    {
        if ($node->name instanceof Node\Identifier && $node->name->name === 'class') {
            return $this->from($node->class);
        }

        if ($node->class instanceof Node\Name && in_array($node->class->name, ['self', 'static'])) {
            return $this->scope->getConstant($node->name->name);
        }

        $fqn = $this->scope->getUse($node->class->name);

        return $this->reflector->constantType($node->name->name, $fqn, $node);
    }

    public function resolveForCondition(Node\Expr\ClassConstFetch $node)
    {
        return $this->fromOutsideOfCondition($node);
    }
}
