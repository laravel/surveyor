<?php

namespace StaticAnalyzer\Analysis;

class ControlFlowVariableTracker
{
    protected array $branches = [];
    protected array $variableSnapshots = [];
    protected array $pathConditions = [];

    public function addBranch(string $condition, int $startLine, int $mergePoint): ExecutionBranch
    {
        $branch = new ExecutionBranch($condition, $startLine, $mergePoint);
        $this->branches[] = $branch;
        return $branch;
    }

    public function setVariableState(int $lineNumber, string $variableName, mixed $value, array $pathConditions = []): void
    {
        if (!isset($this->variableSnapshots[$lineNumber])) {
            $this->variableSnapshots[$lineNumber] = [];
        }

        if (!isset($this->variableSnapshots[$lineNumber][$variableName])) {
            $this->variableSnapshots[$lineNumber][$variableName] = new VariableState($variableName);
        }

        $this->variableSnapshots[$lineNumber][$variableName]->addPossibleValue($value, $pathConditions);
    }

    public function getPossibleValuesAt(int $lineNumber, string $variableName): array
    {
        $possibleValues = [];
        $reachingPaths = $this->getReachingPathsAt($lineNumber);

        foreach ($reachingPaths as $path) {
            $value = $this->getVariableValueInPath($variableName, $lineNumber, $path);
            if ($value !== null) {
                $possibleValues[] = $value;
            }
        }

        return array_unique($possibleValues);
    }

    protected function getReachingPathsAt(int $lineNumber): array
    {
        $paths = [];

        // Find all execution paths that reach this line
        foreach ($this->branches as $branch) {
            if ($branch->getMergePoint() >= $lineNumber) {
                // Check if line is in true path
                if ($this->isLineInPath($lineNumber, $branch->getTruePath())) {
                    $paths[] = $branch->getTruePathConditions();
                }

                // Check if line is in false path
                if ($this->isLineInPath($lineNumber, $branch->getFalsePath())) {
                    $paths[] = $branch->getFalsePathConditions();
                }
            }
        }

        // If no specific paths found, return empty path (unconditional)
        if (empty($paths)) {
            $paths[] = [];
        }

        return $paths;
    }

    protected function isLineInPath(int $lineNumber, array $pathLines): bool
    {
        return in_array($lineNumber, $pathLines);
    }

    protected function getVariableValueInPath(string $variableName, int $atLine, array $pathConditions): mixed
    {
        $lastValue = null;

        // Walk backwards from the target line to find the most recent assignment
        for ($line = $atLine; $line >= 1; $line--) {
            if (isset($this->variableSnapshots[$line][$variableName])) {
                $variableState = $this->variableSnapshots[$line][$variableName];
                $value = $variableState->getValueForPath($pathConditions);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return $lastValue;
    }

    public function getVariableSnapshots(): array
    {
        return $this->variableSnapshots;
    }

    public function getBranches(): array
    {
        return $this->branches;
    }
}

class ExecutionBranch
{
    protected string $condition;
    protected int $startLine;
    protected int $mergePoint;
    protected array $truePath = [];
    protected array $falsePath = [];

    public function __construct(string $condition, int $startLine, int $mergePoint)
    {
        $this->condition = $condition;
        $this->startLine = $startLine;
        $this->mergePoint = $mergePoint;
    }

    public function setTruePath(array $lines): void
    {
        $this->truePath = $lines;
    }

    public function setFalsePath(array $lines): void
    {
        $this->falsePath = $lines;
    }

    public function getCondition(): string
    {
        return $this->condition;
    }

    public function getStartLine(): int
    {
        return $this->startLine;
    }

    public function getMergePoint(): int
    {
        return $this->mergePoint;
    }

    public function getTruePath(): array
    {
        return $this->truePath;
    }

    public function getFalsePath(): array
    {
        return $this->falsePath;
    }

    public function getTruePathConditions(): array
    {
        return [$this->condition => true];
    }

    public function getFalsePathConditions(): array
    {
        return [$this->condition => false];
    }
}

class VariableState
{
    protected string $variableName;
    protected array $possibleValues = [];

    public function __construct(string $variableName)
    {
        $this->variableName = $variableName;
    }

    public function addPossibleValue(mixed $value, array $pathConditions = []): void
    {
        $this->possibleValues[] = [
            'value' => $value,
            'conditions' => $pathConditions
        ];
    }

    public function getValueForPath(array $pathConditions): mixed
    {
        // Find the value that matches the given path conditions
        foreach ($this->possibleValues as $possibility) {
            if ($this->pathConditionsMatch($possibility['conditions'], $pathConditions)) {
                return $possibility['value'];
            }
        }

        // If no specific match, return the first unconditional value
        foreach ($this->possibleValues as $possibility) {
            if (empty($possibility['conditions'])) {
                return $possibility['value'];
            }
        }

        return null;
    }

    protected function pathConditionsMatch(array $assignmentConditions, array $pathConditions): bool
    {
        if (empty($assignmentConditions) && empty($pathConditions)) {
            return true;
        }

        foreach ($assignmentConditions as $condition => $expectedValue) {
            if (!isset($pathConditions[$condition]) || $pathConditions[$condition] !== $expectedValue) {
                return false;
            }
        }

        return true;
    }

    public function getPossibleValues(): array
    {
        return $this->possibleValues;
    }

    public function getVariableName(): string
    {
        return $this->variableName;
    }
}
