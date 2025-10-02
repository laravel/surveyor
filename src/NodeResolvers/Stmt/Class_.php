<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\Analysis\EntityType;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;
use ReflectionClass;

class Class_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Class_ $node)
    {
        $this->scope->setEntityName($node->namespacedName->name);
        $this->scope->setEntityType(EntityType::CLASS_TYPE);

        $this->parseImplements($node);

        return null;
    }

    protected function parseImplements(Node\Stmt\Class_ $node)
    {
        foreach ($node->implements as $interface) {
            $reflection = $this->reflector->reflectClass($interface->toString());

            foreach ($reflection->getConstants() as $key => $value) {
                $this->scope->addConstant($key, Type::from($value));
            }
        }
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
