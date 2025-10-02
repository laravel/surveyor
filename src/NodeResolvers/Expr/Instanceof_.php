<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\Analysis\Condition;
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
        if (! $node->class instanceof Node\Name) {
            return null;
        }

        $className = $node->class->toString();
        $fullClassName = $this->scope->getUse($className);

        return Condition::from($node->expr, new ClassType($fullClassName))
            ->whenTrue(fn (Condition $c) => $c->setType(new ClassType($fullClassName)))
            ->whenFalse(fn (Condition $c) => $c->removeType(new ClassType($fullClassName)))
            ->makeTrue();
    }
}
