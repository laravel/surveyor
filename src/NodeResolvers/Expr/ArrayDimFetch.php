<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\Analysis\Condition;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\ArrayShapeType;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class ArrayDimFetch extends AbstractResolver
{
    public function resolve(Node\Expr\ArrayDimFetch $node)
    {
        $var = $this->from($node->var);
        $dim = $node->dim === null ? Type::int() : $this->from($node->dim);

        if (! Type::is($var, ArrayType::class, ArrayShapeType::class)) {
            // Debug::ddFromClass($var, $node, 'non-array?');
            return Type::mixed();
        }

        if (Type::is($var, ArrayShapeType::class)) {
            return $var->valueType;
        }

        if (property_exists($dim, 'value')) {
            return $var->value[$dim->value] ?? Type::mixed();
        }

        return Type::mixed();
    }

    public function resolveForCondition(Node\Expr\ArrayDimFetch $node)
    {
        return new Condition($node->var->var->name, $this->fromOutsideOfCondition($node));
    }
}
