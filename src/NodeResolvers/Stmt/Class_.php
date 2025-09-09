<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\Analysis\Scope;
use Laravel\StaticAnalyzer\Debug\Debug;
use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Result\ClassDeclaration;
use PhpParser\Node;
use ReflectionClass;

class Class_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Class_ $node)
    {
        $this->scope->setClassName($node->namespacedName->name);

        return null;
        // Debug::log('Resolving Class: ' . $node->namespacedName->name);

        // $extends = $this->getAllExtends($node);

        // $this->scope->setClassName($node->namespacedName->name);

        // return (new ClassDeclaration(
        //     name: $node->namespacedName->name,
        //     extends: $extends,
        //     implements: array_map(fn($node) => $node->toString(), $node->implements),
        //     properties: $this->getAllProperties($node),
        //     methods: $this->getAllMethods($node),
        //     constants: $this->getAllConstants($node),
        // ))->fromNode($node);
    }

    public function scope(): Scope
    {
        // TODO: What about anonymous classes?
        return $this->scope->newChildScope();
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
