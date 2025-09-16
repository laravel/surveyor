<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\NodeResolvers\Shared\ResolvesPropertyFetches;
use PhpParser\Node;

class NullsafePropertyFetch extends AbstractResolver
{
    use ResolvesPropertyFetches;

    public function resolve(Node\Expr\NullsafePropertyFetch $node)
    {
        return $this->resolvePropertyFetch($node);
    }
}
