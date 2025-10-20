<?php

namespace Laravel\Surveyor\NodeResolvers;

use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Param extends AbstractResolver
{
    public function resolve(Node\Param $node)
    {
        $type = $this->resolveType($node);

        if ($node->variadic) {
            $type = Type::arrayShape(Type::int(), $type);
        }

        $this->scope->addParameter($node->var->name, $type);

        $this->scope->state()->add(
            $node,
            $type,
        );

        return null;
    }

    protected function resolveType(Node\Param $node)
    {
        $results = [];

        if ($this->scope->entityName() && $this->scope->methodName()) {
            $result = $this->reflector->paramType($node, $this->scope->entityName(), $this->scope->methodName());

            if ($result) {
                $results[] = $result;
            }
        }

        if ($node->type) {
            $results[] = $this->from($node->type);
        }

        if (empty($results)) {
            return Type::mixed();
        }

        return Type::union(...$results);
    }
}
