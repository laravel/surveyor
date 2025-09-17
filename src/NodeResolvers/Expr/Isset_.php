<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\Analysis\Condition;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Isset_ extends AbstractResolver
{
    public function resolve(Node\Expr\Isset_ $node)
    {
        return Type::bool();
    }

    public function resolveForCondition(Node\Expr\Isset_ $node)
    {
        return array_values(
            array_filter(
                array_map(fn($var) => $this->resolveVarForCondition($var, $node), $node->vars),
            ),
        );
    }

    public function resolveVarForCondition(Node\Expr $var, Node\Expr\Isset_ $node)
    {
        if (!$var instanceof Node\Expr\ArrayDimFetch) {
            return Condition::from(
                $var,
                $this->scope->state()->getAtLine($var)->type()
            )
                ->whenTrue(fn($_, TypeContract $type) => $type->nullable(false))
                ->whenFalse(fn($_, TypeContract $type) => $type->nullable(true));
        }

        Debug::ddAndOpen($var, $node, 'array dim fetch, time to deal with this');

        // if ($var->var instanceof Node\Expr\Variable) {
        //     return Condition::from(
        //         $var,
        //         $this->scope->state()->getAtLine($var)->type()
        //     )
        //         ->whenTrue(fn($_, TypeContract $type) => $type->nullable(false))
        //         ->whenFalse(fn($_, TypeContract $type) => $this->scope->state()->variables()->removeArrayKeyType($var->var->name, $var->dim->value, Type::null(), $node));
        //     ;
        // }

        // if ($var->var instanceof Node\Expr\PropertyFetch) {
        //     $key = $this->fromOutsideOfCondition($var->dim);

        //     if (! property_exists($key, 'value')) {
        //         Debug::ddAndOpen($key, $node, 'unknown key');
        //     }

        //     if ($key->value === null) {
        //         // We don't know the key, so we can't unset the array key
        //         return null;
        //     }

        //     $this->scope->state()->properties()->removeArrayKeyType($var->var->name->name, $key->value, Type::null(), $node);
        // }
    }
}
