<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\Analysis\EntityType;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;
use ReflectionClass;

class Class_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Class_ $node)
    {
        $this->scope->setEntityName($node->namespacedName->name);
        $this->scope->setEntityType(EntityType::CLASS_TYPE);

        return null;
    }

    protected function getAllProperties(Node\Stmt\Class_ $node)
    {
        return array_map(fn ($node) => $this->from($node), $node->getProperties());
    }

    protected function getAllMethods(Node\Stmt\Class_ $node)
    {
        return array_map(fn ($node) => $this->from($node), $node->getMethods());
    }

    protected function getAllConstants(Node\Stmt\Class_ $node)
    {
        return array_map(fn ($node) => $this->from($node), $node->getConstants());
    }

    protected function getAllExtends(Node\Stmt\Class_ $node)
    {
        if (! $node->extends) {
            return [];
        }

        $extends = [$node->extends->toString()];
        $extendsClass = $node->extends->toString();

        do {
            $reflection = new ReflectionClass($extendsClass);

            $extendsClass = $reflection->getParentClass();

            if ($extendsClass) {
                $extends[] = $extendsClass->getName();
            }
        } while ($extendsClass);

        return $extends;
    }
}
