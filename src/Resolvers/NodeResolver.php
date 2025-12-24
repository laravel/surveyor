<?php

namespace Laravel\Surveyor\Resolvers;

use Illuminate\Container\Container;
use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Parser\DocBlockParser;
use Laravel\Surveyor\Reflector\Reflector;
use Laravel\Surveyor\Types\Type;
use PhpParser\NodeAbstract;
use Throwable;

class NodeResolver
{
    protected array $resolved = [];

    public function __construct(
        protected Container $app,
        protected DocBlockParser $docBlockParser,
        protected Reflector $reflector,
    ) {
        //
    }

    /**
     * @return array{0: \Laravel\Surveyor\Types\Contracts\Type|null, 1: \Laravel\Surveyor\Analysis\Scope|null}
     */
    public function fromWithScope(NodeAbstract $node, Scope $scope)
    {
        $resolver = $this->resolveClassInstance($node);
        $resolver->setScope($scope);

        try {
            if ($scope->isAnalyzingCondition()) {
                $newScope = $scope;
                $resolved = method_exists($resolver, 'resolveForCondition') ? $resolver->resolveForCondition($node) : null;
            } else {
                $newScope = $resolver->scope() ?? $scope;
                $resolver->setScope($newScope);
                $resolved = $resolver->resolve($node);
            }
        } catch (Throwable $e) {
            Debug::error($e, 'Resolving node');

            return Debug::throwOr($e, fn () => [Type::mixed(), $newScope ?? null]);
        }

        return [$resolved, $newScope];
    }

    /**
     * @return \Laravel\Surveyor\Analysis\Scope
     */
    public function exitNode(NodeAbstract $node, Scope $scope)
    {
        $resolver = $this->resolveClassInstance($node);

        $resolver->setScope($scope);
        $resolver->onExit($node);

        return $resolver->exitScope();
    }

    /**
     * @return \Laravel\Surveyor\NodeResolvers\AbstractResolver
     */
    protected function resolveClassInstance(NodeAbstract $node)
    {
        $className = $this->getClassName($node);

        Debug::log('🧐 Resolving Node: '.$className.' '.$node->getStartLine(), level: 3);

        return new $className($this, $this->docBlockParser, $this->reflector);
    }

    /**
     * @return \Laravel\Surveyor\Types\Contracts\Type|null
     */
    public function from(NodeAbstract $node, Scope $scope)
    {
        return $this->fromWithScope($node, $scope)[0];
    }

    /**
     * @return class-string<\Laravel\Surveyor\NodeResolvers\AbstractResolver>
     */
    protected function getClassName(NodeAbstract $node)
    {
        return $this->resolved[get_class($node)] ??= $this->resolveClass($node);
    }

    /**
     * @return class-string<\Laravel\Surveyor\NodeResolvers\AbstractResolver>
     */
    protected function resolveClass(NodeAbstract $node)
    {
        return str(get_class($node))
            ->after('Node\\')
            ->prepend('Laravel\\Surveyor\\NodeResolvers\\')
            ->toString();
    }
}
