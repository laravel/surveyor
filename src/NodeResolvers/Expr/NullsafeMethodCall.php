<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\NodeResolvers\Shared\ResolvesMethodCalls;
use PhpParser\Node;

class NullsafeMethodCall extends AbstractResolver
{
    use ResolvesMethodCalls;

    public function resolve(Node\Expr\NullsafeMethodCall $node)
    {
        $result = $this->resolveMethodCall($node);

        return $result->nullable();
    }
}
