<?php

namespace Laravel\StaticAnalyzer\Analysis;

use Exception;
use Laravel\StaticAnalyzer\Result\StateTracker;
use Laravel\StaticAnalyzer\Types\Contracts\Type;

class Scope
{
    protected ?string $className = null;

    protected ?string $methodName = null;

    protected StateTracker $stateTracker;

    protected array $children = [];

    protected array $uses = [];

    protected ?string $namespace = null;

    protected array $traits = [];

    protected array $constants = [];

    public function __construct(protected ?Scope $parent = null)
    {
        $this->stateTracker = new StateTracker;
    }

    public function addConstant(string $constant, Type $type): void
    {
        $this->constants[$constant] = $type;
    }

    public function getConstant(string $constant): ?Type
    {
        if (! array_key_exists($constant, $this->constants)) {
            if ($this->parent) {
                return $this->parent->getConstant($constant);
            }

            throw new Exception('Constant '.$constant.' not found');
        }

        return $this->constants[$constant] ?? throw new Exception('Constant '.$constant.' not found');
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

    public function addTrait(string $trait): void
    {
        $this->traits[] = $trait;
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

        if ($this->parent) {
            return $this->parent->getUse($candidate);
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

    public function variables()
    {
        return $this->stateTracker->variables();
    }

    public function properties()
    {
        return $this->stateTracker->properties();
    }

    public function methodScope(string $methodName): Scope
    {
        return collect($this->children)->first(fn ($child) => $child->methodName() === $methodName);
    }
}
