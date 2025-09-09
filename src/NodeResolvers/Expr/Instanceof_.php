<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\Debug\Debug;
use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\ClassType;
use PhpParser\Node;

class Instanceof_ extends AbstractResolver
{
    public function resolve(Node\Expr\Instanceof_ $node)
    {
        return \Laravel\StaticAnalyzer\Types\Type::bool();
    }

    public function resolveForCondition(Node\Expr\Instanceof_ $node)
    {
        if ($node->class instanceof Node\Name) {
            $className = $node->class->toString();
            $fullClassName = $this->scope->getUse($className);
        } else {
            Debug::ddFromClass($node->class, $node, 'unknown class');
        }

        $expr = $node->expr;

        if ($expr instanceof Node\Expr\Variable) {
            $this->scope->variables()->narrow($expr->name, new ClassType($fullClassName), $node->getStartLine());
        } else {
            Debug::ddFromClass($expr, $node, 'unknown expression');
        }
    }
}
