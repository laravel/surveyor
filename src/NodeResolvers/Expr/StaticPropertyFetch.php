<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\ClassType;
use PhpParser\Node;

class StaticPropertyFetch extends AbstractResolver
{
    public function resolve(Node\Expr\StaticPropertyFetch $node)
    {
        $class = $this->from($node->class);

        if (! $class instanceof ClassType) {
            dd('property fetch but not a class type??', $node->name, $node->class, $class, $this->scope);
        }

        return $this->reflector->propertyType($node->name, $class, $node);
    }
}
