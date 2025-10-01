<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;
use PhpParser\Node;

trait ResolvesPropertyFetches
{
    protected function resolvePropertyFetch(
        Node\Expr\PropertyFetch|Node\Expr\NullsafePropertyFetch|Node\Expr\StaticPropertyFetch $node,
    ) {
        $type = $node instanceof Node\Expr\StaticPropertyFetch ? $this->from($node->class) : $this->from($node->var);

        if ($type instanceof UnionType) {
            foreach ($type->types as $type) {
                if ($type instanceof ClassType) {
                    return $this->reflector->propertyType($node->name, $type, $node);
                }
            }
        }

        if (! $type instanceof ClassType) {
            return Type::mixed();
        }

        if ($node->name instanceof Node\Expr\Variable) {
            $result = $this->from($node->name);

            if (! Type::is($result, StringType::class) || $result->value === null) {
                return Type::mixed();
            }

            return $this->reflector->propertyType($result->value, $type, $node);
        }

        return $this->reflector->propertyType($node->name, $type, $node);
    }
}
