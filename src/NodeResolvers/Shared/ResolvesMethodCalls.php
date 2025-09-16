<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

trait ResolvesMethodCalls
{
    protected function resolveMethodCall(Node\Expr\MethodCall|Node\Expr\NullsafeMethodCall $node)
    {
        $var = $this->from($node->var);

        if (! $var instanceof ClassType) {
            Debug::ddAndOpen($var, $node, 'non-class for method call?');
        }

        return Type::union(
            ...$this->reflector->methodReturnType($this->scope->getUse($var->value), $node->name, $node)
        );
    }
}
