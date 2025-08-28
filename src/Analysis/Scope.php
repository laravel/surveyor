<?php

namespace Laravel\StaticAnalyzer\Analysis;

use Laravel\StaticAnalyzer\Result\StateTracker;

class Scope
{
    protected ?string $className = null;

    protected ?string $methodName = null;

    protected StateTracker $stateTracker;

    protected array $children = [];

    protected array $uses = [];

    protected ?string $namespace = null;

    public function __construct(protected ?Scope $parent = null)
    {
        $this->stateTracker = new StateTracker;
    }

    public function setClassName(string $className): void
    {
        $this->className = $className;
        $this->stateTracker->setThis($className);
    }

    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    public function newChildScope(): self
    {
        $instance = new self($this);

        if ($this->className) {
            $instance->setClassName($this->className);
        }

        if ($this->methodName) {
            $instance->setMethodName($this->methodName);
        }

        $this->children[] = $instance;

        return $instance;
    }

    public function addUse(string $use): void
    {
        $this->uses[] = $use;
    }

    public function getUse(string $candidate): ?string
    {
        if ($candidate === 'static' || $candidate === 'self') {
            return $this->className;
        }

        foreach ($this->uses as $use) {
            if ($candidate === $use || str_ends_with($use, '\\'.$candidate)) {
                return $use;
            }
        }

        if ($this->namespace && class_exists($this->namespace.'\\'.$candidate)) {
            return $this->namespace.'\\'.$candidate;
        }

        return null;
    }

    public function setMethodName(string $methodName): void
    {
        $this->methodName = $methodName;
    }

    public function className(): ?string
    {
        return $this->className;
    }

    public function methodName(): ?string
    {
        return $this->methodName;
    }

    public function stateTracker()
    {
        return $this->stateTracker;
    }

    public function methodScope(string $methodName): Scope
    {
        return collect($this->children)->first(fn ($child) => $child->methodName() === $methodName);
    }
}
