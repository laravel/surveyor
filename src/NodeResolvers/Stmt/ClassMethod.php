<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\Analysis\ReturnTypeAnalyzer;
use Laravel\StaticAnalyzer\Analysis\Scope;
use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ClassMethod extends AbstractResolver
{
    public function resolve(Node\Stmt\ClassMethod $node)
    {
        $this->scope->setMethodName($node->name);

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

    // protected function getAllReturnTypes(Node\Stmt\ClassMethod $node)
    // {
    //     $analyzer = new ReturnTypeAnalyzer(
    //         $this->resolver,
    //         $this->docBlockParser,
    //         $this->reflector,
    //         $this->scope,
    //     );

    //     return $analyzer->analyze($node, $this->scope);
    // }
}
