<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Result\ClassDeclaration;
use PhpParser\Node;
use ReflectionClass;

class Class_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Class_ $node)
    {
        $extends = $this->getAllExtends($node);

        return (new ClassDeclaration(
            name: $node->name->toString(),
            extends: $extends,
            implements: array_map(fn($node) => $node->toString(), $node->implements),
            properties: $this->getAllProperties($node),
            methods: $this->getAllMethods($node),
            constants: $this->getAllConstants($node),
        ))->fromNode($node);
    }

    protected function getAllProperties(Node\Stmt\Class_ $node)
    {
        return array_map(fn($node) => $this->from($node), $node->getProperties());
    }

    protected function getAllMethods(Node\Stmt\Class_ $node)
    {
        return array_map(fn($node) => $this->from($node), $node->getMethods());
    }

    protected function getAllConstants(Node\Stmt\Class_ $node)
    {
        return array_map(fn($node) => $this->from($node), $node->getConstants());
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
