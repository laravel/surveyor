<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\Analysis\Condition;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\NodeResolvers\Shared\ResolvesAssigns;
use PhpParser\Node;

class Assign extends AbstractResolver
{
    use ResolvesAssigns;

    public function resolve(Node\Expr\Assign $node)
    {
        return $this->resolveAssign($node);
    }

    public function resolveForCondition(Node\Expr\Assign $node)
    {
        $this->scope->analyzingConditionPaused(true);
        $this->resolve($node);
        $this->scope->analyzingConditionPaused(false);

        if (
            $node->var instanceof Node\Expr\Variable
            || $node->var instanceof Node\Expr\PropertyFetch
            || $node->var instanceof Node\Expr\StaticPropertyFetch
        ) {
            $result = $this->from($node->expr) ?? $this->fromOutsideOfCondition($node->expr);

            if ($result === null) {
                return null;
            }

            return new Condition($node->var, $result);
        }

        if ($node->var instanceof Node\Expr\ArrayDimFetch) {
            return new Condition($node->var, $this->from($node->expr));
        }

        Debug::ddAndOpen($node, 'assign: variable but not a variable??');
    }
}
