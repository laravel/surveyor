<?php

namespace Laravel\StaticAnalyzer\NodeResolvers;

use Laravel\StaticAnalyzer\Result\Param as ResultParam;
use Laravel\StaticAnalyzer\Types\Type;
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
            $node->getStartLine(),
        );

        return null;
        // return (new ResultParam(
        //     name: $node->var->name,
        //     type: $this->from($node->type),
        //     isVariadic: $node->variadic,
        //     isReference: $node->byRef,
        // ))->fromNode($node);
    }

    protected function resolveType(Node\Param $node)
    {
        $results = [];

        if ($this->scope->className() && $this->scope->methodName()) {
            $result = $this->reflector->paramType($node, $this->scope->className(), $this->scope->methodName());

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
