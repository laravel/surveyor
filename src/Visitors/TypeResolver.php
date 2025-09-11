<?php

namespace Laravel\Surveyor\Visitors;

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Resolvers\NodeResolver;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class TypeResolver extends NodeVisitorAbstract
{
    /**
     * @var Scope[]
     */
    protected $scopes = [];

    protected Scope $scope;

    public function __construct(
        protected NodeResolver $resolver,
    ) {
        //
    }

    public function scope()
    {
        return $this->scope;
    }

    public function scopes()
    {
        return $this->scopes;
    }

    public function newScope()
    {
        if (isset($this->scope)) {
            $this->scopes[] = $this->scope;
        }

        $this->scope = new Scope;
    }

    public function enterNode(Node $node)
    {
        Debug::log('❗ Entering Node: '.$node->getType().' '.$node->getStartLine(), level: 3);
        Debug::increaseDepth();

        // try {
        [$resolved, $scope] = $this->resolver->fromWithScope($node, $this->scope);
        // } catch (\Throwable $e) {
        //     dd($node, $e->getMessage());
        // }

        $this->scope = $scope;

        // return $resolved;
    }

    public function leaveNode(Node $node)
    {
        $this->scope = $this->resolver->exitNode($node, $this->scope);
        Debug::decreaseDepth();
    }
}
