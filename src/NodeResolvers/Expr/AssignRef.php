<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\Analysis\Condition;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\NodeResolvers\Shared\ResolvesAssigns;
use Laravel\Surveyor\Result\VariableState;
use PhpParser\Node;

class AssignRef extends AbstractResolver
{
    use ResolvesAssigns;

    public function resolve(Node\Expr\AssignRef $node)
    {
        $result = $this->resolveAssign($node);

        if ($result instanceof VariableState) {
            if (property_exists($node->expr, 'name')) {
                $reference = $node->expr->name;
            } else {
                $reference = $this->from($node->expr);
            }

            if (is_string($reference)) {
                $result->addReference($reference);
            }
        }

        return $result;
    }

    public function resolveForCondition(Node\Expr\AssignRef $node)
    {
        $this->resolve($node);

        if ($node->var instanceof Node\Expr\Variable) {
            return new Condition($node->var, $this->from($node->expr));
        }

        Debug::ddAndOpen($node, 'assign ref: variable but not a variable??');
    }
}
