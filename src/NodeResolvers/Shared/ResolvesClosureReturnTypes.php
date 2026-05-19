<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use PhpParser\Node;

trait ResolvesClosureReturnTypes
{
    protected function resolveClosureReturnType(Node\Expr $expr): ?TypeContract
    {
        if ($expr instanceof Node\Expr\ArrowFunction) {
            if ($expr->returnType) {
                return $this->from($expr->returnType);
            }

            return $this->from($expr->expr);
        }

        if ($expr instanceof Node\Expr\Closure) {
            if ($expr->returnType) {
                return $this->from($expr->returnType);
            }

            foreach ($expr->stmts as $stmt) {
                if ($stmt instanceof Node\Stmt\Return_ && $stmt->expr !== null) {
                    return $this->from($stmt->expr);
                }
            }
        }

        return null;
    }

    /**
     * Resolve a closure's return type, injecting known types for untyped params.
     * Used when the collection's template types (e.g. TValue=string) are available
     * and should flow into the closure body.
     *
     * @param array<int, TypeContract|null> $paramTypes Resolved types indexed by param position
     */
    protected function resolveClosureReturnTypeWithParamHints(Node\Expr $expr, array $paramTypes): ?TypeContract
    {
        if ($expr instanceof Node\Expr\ArrowFunction) {
            if ($expr->returnType) {
                return $this->from($expr->returnType);
            }

            foreach ($expr->params as $i => $param) {
                if ($param->type === null && isset($paramTypes[$i]) && $paramTypes[$i] !== null) {
                    $this->scope->state()->add($param, $paramTypes[$i]);
                }
            }

            return $this->from($expr->expr);
        }

        if ($expr instanceof Node\Expr\Closure) {
            if ($expr->returnType) {
                return $this->from($expr->returnType);
            }

            foreach ($expr->params as $i => $param) {
                if ($param->type === null && isset($paramTypes[$i]) && $paramTypes[$i] !== null) {
                    $this->scope->state()->add($param, $paramTypes[$i]);
                }
            }

            foreach ($expr->stmts as $stmt) {
                if ($stmt instanceof Node\Stmt\Return_ && $stmt->expr !== null) {
                    return $this->from($stmt->expr);
                }
            }
        }

        return null;
    }
}
