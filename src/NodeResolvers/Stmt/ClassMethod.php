<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\Analysis\EntityType;
use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Analyzed\MethodResult;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ClassMethod extends AbstractResolver
{
    public function resolve(Node\Stmt\ClassMethod $node)
    {
        $this->scope->setMethodName($node->name);
        $this->scope->setEntityType(EntityType::METHOD_TYPE);

        $result = new MethodResult(
            name: $this->scope->methodName(),
        );

        $this->scope->attachResult($result);

        if ($node->returnType) {
            $returnTypes = $this->from($node->returnType);

            if ($returnTypes) {
                $this->scope->addReturnType($returnTypes, $node->getStartLine());
            }
        }

        return null;
    }

    public function scope(): Scope
    {
        return $this->scope->newChildScope();
    }

    public function exitScope(): Scope
    {
        foreach ($this->scope->parameters() as $parameter) {
            $this->scope->result()->addParameter($parameter->name, $parameter->type);
        }

        foreach ($this->scope->returnTypes() as $returnType) {
            $this->scope->result()->addReturnType($returnType['type'], $returnType['lineNumber']);
        }

        $this->scope->parent()->result()->addMethod(
            $this->scope->result(),
        );

        return $this->scope->parent();
    }
}
