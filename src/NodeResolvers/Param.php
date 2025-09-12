<?php

namespace Laravel\Surveyor\NodeResolvers;

use Laravel\Surveyor\Debug\Debug;
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

        $this->scope->variables()->add(
            $node->var->name,
            $type,
            $node,
        );

        return null;
    }

    protected function resolveType(Node\Param $node)
    {
        $results = [];

        if ($this->scope->entityName() && $this->scope->methodName()) {
            Debug::interested($node->var->name === 'callback');
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
