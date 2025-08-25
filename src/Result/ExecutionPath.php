<?php

namespace Laravel\StaticAnalyzer\Result;

use Laravel\StaticAnalyzer\Types\Contracts\Type;

class ExecutionPath
{
    /** @var array<string, VariableState> */
    protected array $variables = [];

    protected bool $terminated = false;

    public function __construct(
        public string $pathId,
        public array $conditions = [],
        public ?ExecutionPath $parent = null,
        public ?int $startLine = null,
        public ?int $endLine = null
    ) {
        // Inherit variables from parent path
        if ($parent) {
            $this->variables = $parent->variables;
        }
    }

    public function setVariable(string $name, Type $type, int $lineNumber): void
    {
        if ($this->terminated) {
            return; // Don't update variables after early return
        }

        $this->variables[$name] = new VariableState($name, $type, $lineNumber, $this->pathId);
    }

    public function getVariable(string $name): ?VariableState
    {
        return $this->variables[$name] ?? null;
    }

    public function getAllVariables(): array
    {
        return $this->variables;
    }

    public function terminate(int $lineNumber): void
    {
        $this->terminated = true;
    }

    public function isTerminated(): bool
    {
        return $this->terminated;
    }

    public function fork(string $newPathId, array $additionalConditions = [], ?int $startLine = null, ?int $endLine = null): ExecutionPath
    {
        if ($this->terminated) {
            // Can't fork from terminated path
            $fork = new ExecutionPath($newPathId, array_merge($this->conditions, $additionalConditions), $this, $startLine, $endLine);
            $fork->terminate(0); // Mark as terminated immediately
            return $fork;
        }

        return new ExecutionPath(
            $newPathId,
            array_merge($this->conditions, $additionalConditions),
            $this,
            $startLine,
            $endLine
        );
    }

    public function canReachLine(int $lineNumber): bool
    {
        // Simple heuristic: if path is terminated and we're asking about a line
        // after termination, it's not reachable
        return !$this->terminated;
    }

    public function getLatestVariableBeforeLine(string $name, int $lineNumber): ?VariableState
    {
        $latestVariable = null;

        if (isset($this->variables[$name])) {
            $variable = $this->variables[$name];
            if ($variable->lineNumber <= $lineNumber) {
                $latestVariable = $variable;
            }
        }

        return $latestVariable;
    }

    public function isActiveAtLine(int $lineNumber): bool
    {
        // Check if this path could be executing at the given line
        if ($this->terminated) {
            return false;
        }

        // If we have line range information, use it
        if ($this->startLine !== null && $this->endLine !== null) {
            return $lineNumber >= $this->startLine && $lineNumber <= $this->endLine;
        }

        // If we have only start line, check if we're after it
        if ($this->startLine !== null) {
            return $lineNumber >= $this->startLine;
        }

        // Default: if no line info, assume active
        return true;
    }
}
