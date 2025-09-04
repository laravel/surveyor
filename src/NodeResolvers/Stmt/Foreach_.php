<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\ArrayShapeType;
use Laravel\StaticAnalyzer\Types\ArrayType;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class Foreach_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Foreach_ $node)
    {
        $iterating = $this->from($node->expr);

        if (! $iterating instanceof ArrayType && ! $iterating instanceof ArrayShapeType) {
            dd($node, 'Foreach on non-array or shape?', $iterating);
        }

        if (! $node->keyVar instanceof Node\Expr\Variable && $node->keyVar !== null) {
            dd('foreach keyVar is not a variable??', $node);
        }

        if (! $node->valueVar instanceof Node\Expr\Variable) {
            dd('foreach valueVar is not a variable??', $node);
        }

        if ($node->keyVar) {
            $this->scope->variables()->add(
                $node->keyVar->name,
                $iterating instanceof ArrayShapeType ? $iterating->keyType : Type::mixed(),
                $node->keyVar->getStartLine(),
            );
        }

        $this->scope->variables()->add(
            $node->valueVar->name,
            $iterating instanceof ArrayShapeType ? $iterating->valueType : Type::mixed(),
            $node->valueVar->getStartLine(),
        );

        return null;
    }
}
