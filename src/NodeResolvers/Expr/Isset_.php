<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
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
                $this->scope->variables()->removeType($var->name, $node->getStartLine(), Type::null());
            } elseif ($var instanceof Node\Expr\PropertyFetch) {
                $this->scope->properties()->removeType($var->name->name, $node->getStartLine(), Type::null());
            } elseif ($var instanceof Node\Expr\ArrayDimFetch) {
                if ($var->var instanceof Node\Expr\Variable) {
                    $this->scope->variables()->removeArrayKeyType($var->var->name, $var->dim->value, Type::null(), $node->getStartLine());
                } elseif ($var->var instanceof Node\Expr\PropertyFetch) {
                    $this->scope->properties()->removeArrayKeyType($var->var->name->name, $var->dim->value, Type::null(), $node->getStartLine());
                }
            }
        }
    }
}
