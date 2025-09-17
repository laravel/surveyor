<?php

namespace Laravel\Surveyor\Result;

use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use PhpParser\NodeAbstract;
use PhpParser\Node;

class StateTracker
{
    protected StateTrackerItem $variableTracker;

    protected StateTrackerItem $propertyTracker;

    public function __construct()
    {
        $this->variableTracker = new StateTrackerItem;
        $this->propertyTracker = new StateTrackerItem;
    }

    public function variables()
    {
        return $this->variableTracker;
    }

    public function properties()
    {
        return $this->propertyTracker;
    }

    public function startSnapshot(NodeAbstract $node): void
    {
        $this->variableTracker->startSnapshot($node);
        $this->propertyTracker->startSnapshot($node);
    }

    public function endSnapshot(NodeAbstract $node): void
    {
        $this->variableTracker->endSnapshot($node);
        $this->propertyTracker->endSnapshot($node);
    }

    public function endSnapshotAndCapture(NodeAbstract $node): void
    {
        $this->variableTracker->endSnapshotAndCapture($node);
        $this->propertyTracker->endSnapshotAndCapture($node);
    }

    public function markSnapShotAsTerminated(NodeAbstract $node): void
    {
        $this->variableTracker->markSnapShotAsTerminated($node);
        $this->propertyTracker->markSnapShotAsTerminated($node);
    }

    public function add(NodeAbstract $node, TypeContract $type): void
    {
        $this->route(
            $node,
            fn($node) => $this->variableTracker->add($node->name, $type, $node),
            fn($node) => $this->propertyTracker->add($node->name->name, $type, $node)
        );
    }

    public function get(NodeAbstract $node): ?TypeContract
    {
        return $this->route(
            $node,
            fn($node) => $this->variableTracker->get($node->name),
            fn($node) => $this->propertyTracker->get($node->name->name)
        );
    }

    public function updateArrayKey(NodeAbstract $node, string $key, TypeContract $type, ?NodeAbstract $referenceNode = null): void
    {
        $this->route(
            $node,
            fn($node) => $this->variableTracker->updateArrayKey($node->name, $key, $type, $referenceNode ?? $node),
            fn($node) => $this->propertyTracker->updateArrayKey($node->name->name, $key, $type, $referenceNode ?? $node)
        );
    }

    public function unsetArrayKey(NodeAbstract $node, string $key, ?NodeAbstract $referenceNode = null): void
    {
        $this->route(
            $node,
            fn($node) => $this->variableTracker->unsetArrayKey($node->name, $key, $referenceNode ?? $node),
            fn($node) => $this->propertyTracker->unsetArrayKey($node->name->name, $key, $referenceNode ?? $node)
        );
    }

    public function removeType(NodeAbstract $node, TypeContract $type): void
    {
        $this->route(
            $node,
            fn($node) => $this->variableTracker->removeType($node->name, $node, $type),
            fn($node) => $this->propertyTracker->removeType($node->name->name, $node, $type)
        );
    }

    public function getAtLine(NodeAbstract $node): ?VariableState
    {
        return $this->route(
            $node,
            fn($node) => $this->variableTracker->getAtLine($node->name, $node),
            fn($node) => $this->propertyTracker->getAtLine($node->name->name, $node)
        );
    }

    public function narrow(NodeAbstract $node, TypeContract $type, ?NodeAbstract $referenceNode = null): void
    {
        $this->route(
            $node,
            fn($node) => $this->variableTracker->narrow($node->name, $type, $referenceNode ?? $node),
            fn($node) => $this->propertyTracker->narrow($node->name->name, $type, $referenceNode ?? $node)
        );
    }

    public function unset(NodeAbstract $node, ?NodeAbstract $referenceNode = null): void
    {
        $this->route(
            $node,
            fn($node) => $this->variableTracker->unset($node->name, $referenceNode ?? $node),
            fn($node) => $this->propertyTracker->unset($node->name->name, $referenceNode ?? $node)
        );
    }

    /**
     * @param NodeAbstract $node
     * @param callable(Node\Expr\Variable|Node\Param|Node\StaticVar|Node\Arg) $onVariable
     * @param callable(Node\Expr\PropertyFetch) $onProperty
     * @return mixed
     */
    protected function route(NodeAbstract $node, callable $onVariable, callable $onProperty): mixed
    {
        switch (true) {
            case $node instanceof Node\Expr\Variable:
            case $node instanceof Node\StaticVar:
            case $node instanceof Node\Arg:
                return $onVariable($node);
            case $node instanceof Node\Param:
                return $onVariable($node->var);
            case $node instanceof Node\Expr\PropertyFetch:
            case $node instanceof Node\PropertyItem:
                return $onProperty($node);
            default:
                Debug::ddAndOpen($node, debug_backtrace(limit: 3), 'state route, unknown node type');
                return null;
        }
    }

    public function setThis(string $className): void
    {
        $this->variables()->add('this', new ClassType($className), new class extends NodeAbstract
        {
            public function getType(): string
            {
                return 'NodeAbstract';
            }

            public function getSubNodeNames(): array
            {
                return [];
            }
        });
    }
}
