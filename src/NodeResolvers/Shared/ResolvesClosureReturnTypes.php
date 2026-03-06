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
}
