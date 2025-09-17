<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\ArrayShapeType;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Foreach_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Foreach_ $node)
    {
        $iterating = $this->from($node->expr);

        if (! $iterating instanceof ArrayType && ! $iterating instanceof ArrayShapeType) {
            Debug::ddAndOpen($node, 'Foreach on non-array or shape?', $iterating);
        }

        if (! $node->keyVar instanceof Node\Expr\Variable && $node->keyVar !== null) {
            Debug::ddAndOpen('foreach keyVar is not a variable??', $node);
        }

        if (! $node->valueVar instanceof Node\Expr\Variable) {
            Debug::ddAndOpen('foreach valueVar is not a variable??', $node);
        }

        if ($node->keyVar) {
            $this->scope->state()->add(
                $node->keyVar,
                $iterating instanceof ArrayShapeType ? $iterating->keyType : Type::mixed(),
            );
        }

        $this->scope->state()->add(
            $node->valueVar,
            $iterating instanceof ArrayShapeType ? $iterating->valueType : Type::mixed(),
        );

        return null;
    }
}
