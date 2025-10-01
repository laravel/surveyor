<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\MixedType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

trait ResolvesMethodCalls
{
    protected function resolveMethodCall(Node\Expr\MethodCall|Node\Expr\NullsafeMethodCall $node)
    {
        $var = $this->from($node->var);

        if ($var instanceof MixedType || ! $var instanceof ClassType) {
            return Type::mixed();
        }

        $methodName = $this->from($node->name);

        if (! Type::is($methodName, StringType::class) || $methodName->value === null) {
            return Type::mixed();
        }

        return Type::union(
            ...$this->reflector->methodReturnType(
                $this->scope->getUse($var->value),
                $methodName->value,
                $node,
            ),
        );
    }
}
