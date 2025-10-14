<?php

namespace Laravel\Surveyor\NodeResolvers;

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Parser\DocBlockParser;
use Laravel\Surveyor\Reflector\Reflector;
use Laravel\Surveyor\Resolvers\NodeResolver;
use PhpParser\NodeAbstract;

abstract class AbstractResolver
{
    protected Scope $scope;

    public function __construct(
        protected NodeResolver $resolver,
        protected DocBlockParser $docBlockParser,
        protected Reflector $reflector,
    ) {
        //
    }

    public function setScope(Scope $scope)
    {
        $this->scope = $scope;
        $this->reflector->setScope($scope);
        $this->docBlockParser->setScope($scope);
    }

    public function onExit(NodeAbstract $node)
    {
        //
    }

    public function exitScope(): Scope
    {
        return $this->scope;
    }

    protected function from(NodeAbstract $node)
    {
        Debug::log('🔍 Analyzing Node: '.$node->getType(), level: 3);

        return $this->resolver->from($node, $this->scope);
    }

    protected function fromOutsideOfCondition(NodeAbstract $node)
    {
        $this->scope->pauseConditionAnalysis();
        $result = $this->resolve($node);
        $this->scope->resumeConditionAnalysis();

        return $result;
    }

    public function scope(): Scope
    {
        return $this->scope;
    }
}
