<?php

namespace Laravel\Surveyor\Resolvers;

use Illuminate\Container\Container;
use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Parser\DocBlockParser;
use Laravel\Surveyor\Reflector\Reflector;
use Laravel\Surveyor\Types\Type;
use PhpParser\NodeAbstract;

class NodeResolver
{
    protected array $resolved = [];

    protected array $resolvers = [];

    public function __construct(
        protected Container $app,
        protected DocBlockParser $docBlockParser,
        protected Reflector $reflector,
    ) {
        //
    }

    public function fromWithScope(NodeAbstract $node, Scope $scope)
    {
        $className = $this->getClassName($node);

        Debug::log('🧐 Resolving Node: '.$className.' '.$node->getStartLine(), level: 3);

        $this->resolvers[$className] ??= new $className($this, $this->docBlockParser, $this->reflector);

        $this->resolvers[$className]->setScope($scope);

        $resolver = $this->resolvers[$className];

        try {
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
        } catch (\Throwable $e) {
            return [Type::mixed(), $newScope];
        }

        return [$resolved, $newScope];
    }

    public function exitNode(NodeAbstract $node, Scope $scope)
    {
        $className = $this->getClassName($node);

        // Clone cached resolver to avoid state conflicts during recursive calls
        $resolver = new $className($this, $this->docBlockParser, $this->reflector);

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
        return $this->resolved[get_class($node)] ??= $this->resolveClass($node);

        // if (! class_exists($className)) {
        //     throw new InvalidArgumentException("NodeResolver: Class {$className} does not exist");
        // }

        // return $className;
    }

    protected function resolveClass(NodeAbstract $node)
    {
        return str(get_class($node))
            ->after('Node\\')
            ->prepend('Laravel\\Surveyor\\NodeResolvers\\')
            ->toString();
    }
}
