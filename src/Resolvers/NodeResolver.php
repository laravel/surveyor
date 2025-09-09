<?php

namespace Laravel\StaticAnalyzer\Resolvers;

use Illuminate\Container\Container;
use InvalidArgumentException;
use Laravel\StaticAnalyzer\Analysis\Scope;
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

        $resolver = $this->app->make($className);

        $resolver->setScope($scope);

        if ($scope->isAnalyzingCondition()) {
            // TODO: Is this right? Might not be
            $newScope = $scope;
            $resolved = $resolver->resolveForCondition($node);
        } else {
            $newScope = $resolver->scope() ?? $scope;
            $resolver->setScope($newScope);
            $resolved = $resolver->resolve($node);
        }

        if (is_array($resolved)) {
            dd($resolved, $className);
        }

        return [$resolved, $newScope];
    }

    public function exitNode(NodeAbstract $node, Scope $scope)
    {
        $className = $this->getClassName($node);

        $resolver = $this->app->make($className);
        $resolver->setScope($scope);

        return $resolver->exitScope();
    }

    public function from(NodeAbstract $node, Scope $scope)
    {
        return $this->fromWithScope($node, $scope)[0];
    }

    protected function getClassName(NodeAbstract $node)
    {
        $className = str(get_class($node))->after('Node\\')->prepend('Laravel\\StaticAnalyzer\\NodeResolvers\\')->toString();

        if (! class_exists($className)) {
            throw new InvalidArgumentException("NodeResolver: Class {$className} does not exist");
        }

        return $className;
    }
}
