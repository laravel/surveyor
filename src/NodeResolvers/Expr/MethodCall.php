<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\NodeResolvers\Shared\ResolvesMethodCalls;
use PhpParser\Node;

class MethodCall extends AbstractResolver
{
    use ResolvesMethodCalls;

    public function resolve(Node\Expr\MethodCall $node)
    {
        return $this->resolveMethodCall($node);
    }

    public function resolveForCondition(Node\Expr\MethodCall $node)
    {
        return $this->fromOutsideOfCondition($node);
    }
}
