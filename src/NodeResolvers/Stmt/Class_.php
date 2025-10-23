<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\Analysis\EntityType;
use Laravel\Surveyor\Analyzed\ClassResult;
use Laravel\Surveyor\Analyzed\MethodResult;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Class_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Class_ $node)
    {
        $this->scope->setEntityName($node->namespacedName->name);
        $this->scope->setEntityType(EntityType::CLASS_TYPE);

        $this->parseImplements($node);
        $this->parseExtends($node);

        $result = new ClassResult(
            name: $this->scope->entityName(),
            namespace: $this->scope->namespace(),
            extends: $this->scope->extends(),
            implements: $this->scope->implements(),
            uses: $this->scope->uses(),
        );

        $this->scope->attachResult($result);

        if ($node->getDocComment()) {
            $properties = $this->docBlockParser->parseProperties($node->getDocComment());

            foreach ($properties as $name => $type) {
                $this->scope->state()->addDocBlockProperty($name, $type);
            }

            $methods = $this->docBlockParser->parseMethods($node->getDocComment());

            foreach ($methods as $name => $type) {
                $scope = $this->scope->newChildScope();
                $scope->setMethodName($name);
                $scope->setEntityType(EntityType::METHOD_TYPE);
                $scope->addReturnType($type, 0);

                $methodResult = new MethodResult(
                    name: $scope->methodName(),
                );

                foreach ($scope->parameters() as $parameter) {
                    $methodResult->addParameter($parameter->name, $parameter->type);
                }

                foreach ($scope->returnTypes() as $returnType) {
                    $methodResult->addReturnType($returnType['type'], $returnType['lineNumber']);
                }

                $result->addMethod($methodResult);
            }
        }

        return null;
    }

    protected function parseImplements(Node\Stmt\Class_ $node)
    {
        foreach ($node->implements as $interface) {
            $this->scope->addImplement($interface->toString());

            $reflection = $this->reflector->reflectClass($interface->toString());

            foreach ($reflection->getConstants() as $key => $value) {
                $this->scope->addConstant($key, Type::from($value));
            }
        }
    }

    protected function parseExtends(Node\Stmt\Class_ $node)
    {
        if (! $node->extends) {
            return;
        }

        $extends = [$node->extends->toString()];
        $extendsClass = $this->reflector->reflectClass($node->extends->toString());

        do {
            $extendsClass = $extendsClass->getParentClass();

            if ($extendsClass) {
                $extends[] = $extendsClass->getName();
            }
        } while ($extendsClass);

        foreach ($extends as $extend) {
            $this->scope->addExtend($extend);
        }
    }
}
