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
use ReflectionMethod;
use Throwable;

class NodeResolver
{
    protected array $resolved = [];

    /** @var array<class-string<AbstractResolver>, bool> */
    protected array $hasExitBehaviour = [];

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
     * @return Scope
     */
    public function exitNode(NodeAbstract $node, Scope $scope)
    {
        $className = $this->getClassName($node);

        // Almost every resolver inherits onExit() and exitScope() unchanged, so
        // exiting does nothing but hand back the same scope. Building one just
        // to find that out costs an object on every node in the tree.
        if (! ($this->hasExitBehaviour[$className] ??= $this->resolverHasExitBehaviour($className))) {
            return $scope;
        }

        $resolver = $this->resolveClassInstance($node);

        $resolver->setScope($scope);
        $resolver->onExit($node);

        return $resolver->exitScope();
    }

    /**
     * @param  class-string<AbstractResolver>  $className
     */
    protected function resolverHasExitBehaviour(string $className): bool
    {
        try {
            return (new ReflectionMethod($className, 'onExit'))->class !== AbstractResolver::class
                || (new ReflectionMethod($className, 'exitScope'))->class !== AbstractResolver::class;
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * @return AbstractResolver
     */
    protected function resolveClassInstance(NodeAbstract $node)
    {
        $className = $this->getClassName($node);

        if (Debug::$logLevel >= Debug::TRACE) {
            Debug::log('🧐 Resolving Node: '.$className.' '.$node->getStartLine());
        }

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
        return str(get_class($node))
            ->after('Node\\')
            ->prepend('Laravel\\Surveyor\\NodeResolvers\\')
            ->toString();
    }
}
