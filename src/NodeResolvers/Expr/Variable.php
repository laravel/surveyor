<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\Analysis\Condition;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Contracts\MultiType;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Variable extends AbstractResolver
{
    public function resolve(Node\Expr\Variable $node)
    {
        if (! $node->name instanceof Node\Expr\Variable) {
            return $this->scope->state()->getAtLine($node)?->type() ?? Type::mixed();
        }

        // The ol' double dollar ($$key)
        // TODO: Deal with this later
        return Type::mixed();

        // $result = $this->from($node->name);

        // if ($result instanceof MultiType) {
        //     return Type::union(...array_map(fn ($type) => $type->value, $result->types));
        // }

        // return $result;
    }

    public function resolveForCondition(Node\Expr\Variable $node)
    {
        return Condition::from($node, $this->resolve($node));
    }
}
