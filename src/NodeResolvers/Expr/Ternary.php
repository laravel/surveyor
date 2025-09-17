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

        if ($result instanceof Condition) {
            if (! $result->hasConditions()) {
                // Probably checking a variable for truthiness
                $result->whenTrue(fn($_, TypeContract $type) => $type->nullable(false))
                    ->whenFalse(fn($_, TypeContract $type) => $type->nullable(true));
            }

            $this->scope->state()->add($result->variable, $result->apply(), $node->if);
            $this->scope->state()->add($result->variable, $result->toggle()->apply(), $node->else);
        }

        return Type::union($this->from($node->if), $this->from($node->else));
    }
}
