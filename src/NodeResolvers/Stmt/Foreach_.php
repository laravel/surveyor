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
        $isArrayLike = $iterating instanceof ArrayType || $iterating instanceof ArrayShapeType;

        if (! $isArrayLike) {
            Debug::ddAndOpen($node, $this->scope, 'Foreach on non-array or shape?', $iterating);
        }

        if ($node->keyVar) {
            $this->scope->state()->add(
                $node->keyVar,
                match (true) {
                    $iterating instanceof ArrayType => $iterating->keyType(),
                    $iterating instanceof ArrayShapeType => $iterating->keyType,
                    default => Type::mixed(),
                },
            );
        }

        $this->scope->state()->add(
            $node->valueVar,
            match (true) {
                $iterating instanceof ArrayType => $iterating->valueType(),
                $iterating instanceof ArrayShapeType => $iterating->valueType,
                default => Type::mixed(),
            },
        );

        return null;
    }
}
