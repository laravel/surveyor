<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class New_ extends AbstractResolver
{
    public function resolve(Node\Expr\New_ $node)
    {
        $type = $this->from($node->class);

        if (! property_exists($type, 'value') || $type->value === null) {
            // We couldn't figure it out
            return Type::mixed();
        }

        $classType = new ClassType($this->scope->getUse($type->value));

        $classType->setConstructorArguments(array_map(
            fn ($arg) => $this->from($arg->value),
            $node->args,
        ));

        return $classType;
    }

    public function resolveForCondition(Node\Expr\New_ $node)
    {
        return $this->resolve($node);
    }
}
