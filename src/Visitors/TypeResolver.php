<?php

namespace Laravel\Surveyor\Visitors;

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Resolvers\NodeResolver;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class TypeResolver extends NodeVisitorAbstract
{
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

    public function newScope(string $path)
    {
        $this->scope = new Scope;
        $this->scope->setPath($path);
    }

    public function enterNode(Node $node)
    {
        Debug::increaseDepth();
        // Debug::log('❗ Entering Node: '.$node->getType().' '.$node->getStartLine(), level: 3);

        [$_, $scope] = $this->resolver->fromWithScope($node, $this->scope);

        $this->scope = $scope;
    }

    public function leaveNode(Node $node)
    {
        $this->scope = $this->resolver->exitNode($node, $this->scope);

        Debug::decreaseDepth();
    }
}
