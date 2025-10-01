<?php

namespace Laravel\Surveyor\Resolvers;

use Illuminate\Container\Container;
use InvalidArgumentException;
use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Debug\Debug;
use PhpParser\NodeAbstract;

class NodeResolver
{
    public function __construct(
        protected Container $app,
    ) {
        //
    }

    public function fromWithScope(NodeAbstract $node, Scope $scope)
    {
        $className = $this->getClassName($node);

        Debug::log('🧐 Resolving Node: '.$className.' '.$node->getStartLine(), level: 3);

        $resolver = $this->app->make($className);

        $resolver->setScope($scope);

        if ($scope->isAnalyzingCondition()) {
            // TODO: Is this right? Might not be
            $newScope = $scope;

            if (method_exists($resolver, 'resolveForCondition')) {
                $resolved = $resolver->resolveForCondition($node);
            } else {
                $resolved = null;
            }
        } else {
            $newScope = $resolver->scope() ?? $scope;
            $resolver->setScope($newScope);
            $resolved = $resolver->resolve($node);
        }

        return [$resolved, $newScope];
    }

    public function exitNode(NodeAbstract $node, Scope $scope)
    {
        $className = $this->getClassName($node);

        $resolver = $this->app->make($className);
        $resolver->setScope($scope);

        $resolver->onExit($node);

        return $resolver->exitScope();
    }

    public function from(NodeAbstract $node, Scope $scope)
    {
        return $this->fromWithScope($node, $scope)[0];
    }

    protected function getClassName(NodeAbstract $node)
    {
        $className = str(get_class($node))->after('Node\\')->prepend('Laravel\\Surveyor\\NodeResolvers\\')->toString();

        if (! class_exists($className)) {
            throw new InvalidArgumentException("NodeResolver: Class {$className} does not exist");
        }

        return $className;
    }
}
