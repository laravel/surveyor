<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\Analysis\Condition;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Assign extends AbstractResolver
{
    public function resolve(Node\Expr\Assign $node)
    {
        switch (true) {
            case $node->var instanceof Node\Expr\Variable:
                $this->scope->variables()->add(
                    $node->var->name,
                    $this->from($node->expr),
                    $node
                );
                break;

            case $node->var instanceof Node\Expr\PropertyFetch:
                $this->scope->properties()->add(
                    $node->var->name->name,
                    $this->from($node->expr),
                    $node
                );
                break;

            case $node->var instanceof Node\Expr\ArrayDimFetch:
                $this->resolveForDimFetch($node);
                break;
        }

        return null;
    }

    public function resolveForCondition(Node\Expr\Assign $node)
    {
        if ($node->var instanceof Node\Expr\Variable) {
            return new Condition($node->var->name, $this->from($node->expr));
        }

        Debug::ddFromClass($node, 'assign: variable but not a variable??');
    }

    protected function resolveForDimFetch(Node\Expr\Assign $node)
    {
        /** @var Node\Expr\ArrayDimFetch $dimFetch */
        $dimFetch = $node->var;

        $dim = $dimFetch->dim === null ? Type::int() : $this->from($dimFetch->dim);
        $validDim = Type::is($dim, StringType::class, IntType::class) && $dim->value !== null;

        if (! $validDim) {
            return;
        }

        if ($dimFetch->var instanceof Node\Expr\Variable) {
            $this->scope->variables()->updateArrayKey(
                $dimFetch->var->name,
                $dim->value,
                $this->from($node->expr),
                $node,
            );

            return;
        }

        if ($dimFetch->var instanceof Node\Expr\PropertyFetch) {
            $this->scope->properties()->updateArrayKey(
                $dimFetch->var->name,
                $dim->value,
                $this->from($node->expr),
                $node,
            );

            return;
        }

        dd('assign: array dim fetch but not a variable or property fetch??', $node, $dim);
    }
}
