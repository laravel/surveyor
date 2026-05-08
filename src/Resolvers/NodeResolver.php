<?php

namespace Laravel\Surveyor\Resolvers;

use Illuminate\Container\Container;
use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Parser\DocBlockParser;
use Laravel\Surveyor\Reflector\Reflector;
use Laravel\Surveyor\Types\Type;
use PhpParser\NodeAbstract;
use Throwable;

class NodeResolver
{
    /** @var array<class-string, class-string<AbstractResolver>> */
    protected array $resolved = [];

    /** @var array<class-string<AbstractResolver>, AbstractResolver> */
    protected array $instances = [];

    /** @var array<class-string<AbstractResolver>, bool> */
    protected array $hasResolveForCondition = [];

    public function __construct(
        protected Container $app,
        protected DocBlockParser $docBlockParser,
        protected Reflector $reflector,
    ) {
        //
    }

    /**
     * @return array{0: \Laravel\Surveyor\Types\Contracts\Type|null, 1: Scope|null}
     */
    public function fromWithScope(NodeAbstract $node, Scope $scope)
    {
        $resolver = $this->resolveClassInstance($node);

        $savedResolverScope = $resolver->getScopeOrNull();
        $savedReflectorScope = $this->reflector->getScopeOrNull();
        $savedDocBlockScope = $this->docBlockParser->getScopeOrNull();

        $resolver->setScope($scope);

        $newScope = $scope;
        $resolved = null;

        try {
            if ($scope->isAnalyzingCondition()) {
                $resolved = $this->hasResolveForCondition[$resolver::class] ? $resolver->resolveForCondition($node) : null;
            } else {
                $newScope = $resolver->scope() ?? $scope;
                if ($newScope !== $scope) {
                    $resolver->setScope($newScope);
                }
                $resolved = $resolver->resolve($node);
            }
        } catch (Throwable $e) {
            Debug::error($e, 'Resolving node');

            return Debug::throwOr($e, fn () => [Type::mixed(), $newScope]);
        } finally {
            if ($savedResolverScope !== null) {
                $resolver->setScopeWithoutPropagation($savedResolverScope);
            }
            if ($savedReflectorScope !== null) {
                $this->reflector->setScopeWithoutPropagation($savedReflectorScope);
            }
            if ($savedDocBlockScope !== null) {
                $this->docBlockParser->setScope($savedDocBlockScope);
            }
        }

        return [$resolved, $newScope];
    }

    /**
     * @return Scope
     */
    public function exitNode(NodeAbstract $node, Scope $scope)
    {
        $resolver = $this->resolveClassInstance($node);

        $savedResolverScope = $resolver->getScopeOrNull();
        $savedReflectorScope = $this->reflector->getScopeOrNull();
        $savedDocBlockScope = $this->docBlockParser->getScopeOrNull();

        $resolver->setScope($scope);

        try {
            $resolver->onExit($node);

            return $resolver->exitScope();
        } finally {
            if ($savedResolverScope !== null) {
                $resolver->setScopeWithoutPropagation($savedResolverScope);
            }
            if ($savedReflectorScope !== null) {
                $this->reflector->setScopeWithoutPropagation($savedReflectorScope);
            }
            if ($savedDocBlockScope !== null) {
                $this->docBlockParser->setScope($savedDocBlockScope);
            }
        }
    }

    /**
     * @return AbstractResolver
     */
    protected function resolveClassInstance(NodeAbstract $node)
    {
        $className = $this->getClassName($node);

        return $this->instances[$className] ??= $this->makeInstance($className);
    }

    /**
     * @param  class-string<AbstractResolver>  $className
     */
    protected function makeInstance(string $className): AbstractResolver
    {
        $this->hasResolveForCondition[$className] = method_exists($className, 'resolveForCondition');

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
     * @return class-string<AbstractResolver>
     */
    protected function getClassName(NodeAbstract $node)
    {
        return $this->resolved[get_class($node)] ??= $this->resolveClass($node);
    }

    /**
     * @return class-string<AbstractResolver>
     */
    protected function resolveClass(NodeAbstract $node)
    {
        $class = get_class($node);
        $pos = strpos($class, 'Node\\');

        return 'Laravel\\Surveyor\\NodeResolvers\\'.($pos === false ? $class : substr($class, $pos + 5));
    }
}
