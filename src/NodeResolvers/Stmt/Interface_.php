<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\Analysis\EntityType;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Interface_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Interface_ $node)
    {
        $this->scope->setEntityName($node->namespacedName->name);
        $this->scope->setEntityType(EntityType::INTERFACE_TYPE);

        return null;
    }
}
