<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use Laravel\Surveyor\Analysis\Condition;
use Laravel\Surveyor\Result\VariableState;
use Laravel\Surveyor\Types\ArrayShapeType;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

trait ResolvesAssigns
{
    use ResolvesArrays;

    protected function resolveAssign(Node\Expr\Assign|Node\Expr\AssignRef $node)
    {
        $result = $this->getResult($node);

        if ($this->scope->analyzingConditionPaused()) {
            if ($result instanceof VariableState) {
                // If it's assigned in the condition, it should not be terminated
                $result->markNonTerminable();
            }
        }

        return $result;
    }

    protected function getResult(Node\Expr\Assign|Node\Expr\AssignRef $node)
    {
        // Bug in the parser, assign doc blocks are set in Expression, not Assign
        $pendingDocBlock = $this->scope->getPendingDocBlock();

        if ($pendingDocBlock && $result = $this->docBlockParser->parseVar($pendingDocBlock)) {
            return $this->scope->state()->add($node->var, $result);
        }

        if ($node->var instanceof Node\Expr\ArrayDimFetch) {
            return $this->resolveForDimFetch($node);
        }

        if ($node->var instanceof Node\Expr\List_) {
            $result = [];
            $expr = $this->from($node->expr);

            $values = match (true) {
                $expr instanceof ArrayType => $expr->value,
                $expr instanceof ArrayShapeType => array_fill(0, count($node->var->items), $expr->valueType),
                default => [],
            };

            foreach ($node->var->items as $index => $item) {
                if ($item === null) {
                    continue;
                }

                if ($item->value instanceof Node\Expr\ArrayDimFetch) {
                    $dim = $item->value->dim === null ? Type::int() : $this->from($item->value->dim);
                    $validDim = Type::is($dim, StringType::class, IntType::class) && $dim->value !== null;

                    if ($validDim) {
                        [$varToLookFor, $keys] = $this->resolveArrayVarAndKeys($item->value);

                        $result[] = $this->scope->state()->updateArrayKey(
                            $varToLookFor,
                            $keys,
                            $values[$index] ?? Type::mixed(),
                            $node,
                        );
                    }
                } else {
                    $result[] = $this->scope->state()->add($item->value, $values[$index] ?? Type::mixed());
                }
            }

            return $result;
        }

        $result = $this->from($node->expr);

        if ($result === null) {
            dd($node);
        }

        if ($result instanceof VariableState) {
            $result = $result->type();
        }

        return $this->scope->state()->add($node->var, $result);
    }

    protected function resolveForDimFetch(Node\Expr\Assign|Node\Expr\AssignRef $node)
    {
        /** @var Node\Expr\ArrayDimFetch $dimFetch */
        $dimFetch = $node->var;

        $dim = $dimFetch->dim === null ? Type::int() : $this->from($dimFetch->dim);
        $validDim = Type::is($dim, StringType::class, IntType::class) && $dim->value !== null;

        if ($validDim) {
            [$varToLookFor, $keys] = $this->resolveArrayVarAndKeys($dimFetch);

            $this->scope->state()->updateArrayKey(
                $varToLookFor,
                $keys,
                $this->from($node->expr),
                $node,
            );
        }
    }
}
