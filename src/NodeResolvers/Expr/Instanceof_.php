<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\ClassType;
use PhpParser\Node;

class Instanceof_ extends AbstractResolver
{
    public function resolve(Node\Expr\Instanceof_ $node)
    {
        return \Laravel\Surveyor\Types\Type::bool();
    }

    public function resolveForCondition(Node\Expr\Instanceof_ $node)
    {
        if ($node->class instanceof Node\Name) {
            $className = $node->class->toString();
            $fullClassName = $this->scope->getUse($className);
        } else {
            Debug::ddAndOpen($node->class, $node, 'unknown class');
        }

        $expr = $node->expr;

        if ($expr instanceof Node\Expr\Variable) {
            Debug::interested($expr->name === 'values');
            $this->scope->variables()->narrow($expr->name, new ClassType($fullClassName), $node);
        } else {
            Debug::ddAndOpen($expr, $node, 'unknown expression');
        }
    }
}
