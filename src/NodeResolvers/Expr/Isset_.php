<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
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
        foreach ($node->vars as $var) {
            if ($var instanceof Node\Expr\Variable) {
                $this->scope->variables()->removeType($var->name, $node, Type::null());
            } elseif ($var instanceof Node\Expr\PropertyFetch) {
                $this->scope->properties()->removeType($var->name->name, $node, Type::null());
            } elseif ($var instanceof Node\Expr\ArrayDimFetch) {
                if ($var->var instanceof Node\Expr\Variable) {
                    $this->scope->variables()->removeArrayKeyType($var->var->name, $var->dim->value, Type::null(), $node);
                } elseif ($var->var instanceof Node\Expr\PropertyFetch) {
                    $key = $this->fromOutsideOfCondition($var->dim);

                    if (! property_exists($key, 'value')) {
                        Debug::ddAndOpen($key, $node, 'unknown key');
                    }

                    if ($key->value === null) {
                        // We don't know the key, so we can't unset the array key
                        continue;
                    }

                    $this->scope->properties()->removeArrayKeyType($var->var->name->name, $key->value, Type::null(), $node);
                }
            }
        }
    }
}
