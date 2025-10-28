<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\NodeResolvers\Shared\ResolvesPropertyFetches;
use PhpParser\Node;

class StaticPropertyFetch extends AbstractResolver
{
    use ResolvesPropertyFetches;

    public function resolve(Node\Expr\StaticPropertyFetch $node)
    {
        return $this->resolvePropertyFetch($node);
    }

    public function resolveForCondition(Node\Expr\StaticPropertyFetch $node)
    {
        return $this->resolve($node);
    }
}
