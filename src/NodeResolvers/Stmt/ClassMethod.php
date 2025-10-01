<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ClassMethod extends AbstractResolver
{
    public function resolve(Node\Stmt\ClassMethod $node)
    {
        $this->scope->setMethodName($node->name);

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
        return $this->scope->parent();
    }
}
