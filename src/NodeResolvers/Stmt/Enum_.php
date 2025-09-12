<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\Analysis\EntityType;
use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Enum_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Enum_ $node)
    {
        $this->scope->setEntityName($node->namespacedName->name);
        $this->scope->setEntityType(EntityType::ENUM_TYPE);

        return null;
    }

    public function scope(): Scope
    {
        return $this->scope->newChildScope();
    }
}
