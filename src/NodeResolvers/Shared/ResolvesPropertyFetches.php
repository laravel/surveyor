<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Types\ClassType;
use PhpParser\Node;

trait ResolvesPropertyFetches
{
    protected function resolvePropertyFetch(Node\Expr\PropertyFetch|Node\Expr\NullsafePropertyFetch $node)
    {
        if (! $this->from($node->var) instanceof ClassType) {
            Debug::ddAndOpen($node->name, $node->var, $this->from($node->var), $this->scope, 'property fetch but not a class type??');
        }

        return $this->reflector->propertyType($node->name, $this->from($node->var), $node);
    }
}
