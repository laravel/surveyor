<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\MultiType;
use Laravel\Surveyor\Types\MixedType;
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
                    if ($result = $this->reflector->propertyType($node->name, $type, $node)) {
                        return $result;
                    }
                }
            }
        }

        if (! $type instanceof ClassType) {
            return Type::mixed();
        }

        if ($node->name instanceof Node\Expr\Variable || $node->name instanceof Node\VarLikeIdentifier) {
            $result = $this->from($node->name);

            if (! Type::is($result, StringType::class) || $result->value === null) {
                return Type::mixed();
            }

            return $this->reflector->propertyType($result->value, $type, $node);
        }

        if ($node->name instanceof Node\Expr) {
            $nameType = $this->from($node->name);

            if ($nameType instanceof MultiType) {
                return Type::union(...array_map(
                    fn ($t) => $this->reflector->propertyType($t->value, $type, $node),
                    $nameType->types,
                ));
            }

            if ($nameType instanceof MixedType) {
                return $nameType;
            }

            return $this->reflector->propertyType($nameType->value, $type, $node);
        }

        return $this->reflector->propertyType($node->name, $type, $node);
    }
}
