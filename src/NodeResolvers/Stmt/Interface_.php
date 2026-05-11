<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\Analysis\EntityType;
use Laravel\Surveyor\Analyzed\ClassResult;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Interface_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Interface_ $node)
    {
        $this->scope->setEntityName($node->namespacedName->name);
        $this->scope->setEntityType(EntityType::INTERFACE_TYPE);

        foreach ($node->extends as $extend) {
            $this->scope->addExtend($extend->toString());
        }

        $this->scope->attachResult(new ClassResult(
            name: $this->scope->entityName(),
            namespace: $this->scope->namespace(),
            extends: $this->scope->extends(),
            implements: $this->scope->implements(),
            uses: $this->scope->uses(),
            filePath: $this->scope->fullPath(),
        ));

        return null;
    }
}
