<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\Analysis\Condition;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\NodeResolvers\Shared\ResolvesPropertyFetches;
use PhpParser\Node;

class PropertyFetch extends AbstractResolver
{
    use ResolvesPropertyFetches;

    public function resolve(Node\Expr\PropertyFetch $node)
    {
        return $this->resolvePropertyFetch($node);
    }

    public function resolveForCondition(Node\Expr\PropertyFetch $node)
    {
        $type = $this->fromOutsideOfCondition($node);

        return new Condition($node, $type);
    }
}
