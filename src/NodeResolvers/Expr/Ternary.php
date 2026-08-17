<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\Analysis\Condition;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Ternary extends AbstractResolver
{
    public function resolve(Node\Expr\Ternary $node)
    {
        if ($node->if === null) {
            // e.g. ?:
            return $this->from($node->else);
        }

        $this->scope->startConditionAnalysis();
        $result = $this->from($node->cond);
        $this->scope->endConditionAnalysis();

        if (! $result instanceof Condition) {
            return Type::union($this->from($node->if), $this->from($node->else));
        }

        if (! $result->hasConditions()) {
            // Probably checking a variable for truthiness
            $result->whenTrue(fn ($_, TypeContract $type) => $type->nullable(false))
                ->whenFalse(fn ($_, TypeContract $type) => $type->nullable(true));
        }

        // These are the narrowed types of the value the condition tests, not
        // the types of the branches. Record them so each branch resolves
        // against what the condition proved, then take the branches' own types.
        $if = $result->apply();
        $else = $result->toggle()->apply();

        if ($this->scope->state()->canHandle($node->if)) {
            $this->scope->state()->add($node->if, $if);
        }

        $ifType = $this->from($node->if);

        if ($this->scope->state()->canHandle($node->else)) {
            $this->scope->state()->add($node->else, $else);
        }

        $elseType = $this->from($node->else);

        return Type::union($ifType, $elseType);
    }

    public function resolveForCondition(Node\Expr\Ternary $node)
    {
        return $this->resolve($node);
    }
}
