<?php

namespace Laravel\StaticAnalyzer\Result;

use Laravel\StaticAnalyzer\Types\Contracts\Type;
use Laravel\StaticAnalyzer\Types\Type as TypeFactory;

class VariableTracker
{
    /** @var array<string, ExecutionPath> */
    protected array $activePaths = [];

    protected int $pathCounter = 0;

    protected array $variables = [];

    protected array $snapshots = [];

    protected array $activeSnapshots = [];

    public function __construct()
    {
        $this->activePaths['main'] = new ExecutionPath('main');
    }

    public function add(string $name, Type $type, int $lineNumber): void
    {
        $changed = [
            'type' => $type,
            'lineNumber' => $lineNumber,
        ];

        $this->variables[$name] ??= [];
        $this->variables[$name][] = $changed;

        if (count($this->activeSnapshots) > 0) {
            $activeSnapshot = $this->activeSnapshots[count($this->activeSnapshots) - 1];
            $this->snapshots[$activeSnapshot][$name] ??= [];
            $this->snapshots[$activeSnapshot][$name][] = $changed;
        }
    }

    public function getVariables()
    {
        return $this->variables;
    }

    public function getAtLine(string $name, int $lineNumber): array
    {
        $lines = array_filter($this->variables[$name], fn($variable) => $variable['lineNumber'] <= $lineNumber);

        return end($lines);
    }

    public function startSnapshot(int $startLine): void
    {
        $this->snapshots[$startLine] = [];
        $this->activeSnapshots[] = $startLine;
    }

    public function endSnapshot(int $startLine): array
    {
        $changed = $this->snapshots[$startLine] ?? [];

        array_pop($this->activeSnapshots);
        unset($this->snapshots[$startLine]);

        return $changed;
    }

    public function addVariable(string $name, Type $type, int $lineNumber, string $pathId = 'main'): void
    {
        if (isset($this->activePaths[$pathId])) {
            $this->activePaths[$pathId]->setVariable($name, $type, $lineNumber);
        }
    }

    public function forkPath(string $condition, string $parentPathId = 'main', ?int $startLine = null, ?int $endLine = null): string
    {
        $newPathId = $parentPathId . '-' . (++$this->pathCounter);

        if (isset($this->activePaths[$parentPathId])) {
            $this->activePaths[$newPathId] = $this->activePaths[$parentPathId]->fork($newPathId, [$condition], $startLine, $endLine);
        }

        return $newPathId;
    }

    public function terminatePath(string $pathId, int $lineNumber): void
    {
        if (count($this->activeSnapshots) > 0) {
            $this->endSnapshot(end($this->activeSnapshots));
        }

        if (isset($this->activePaths[$pathId])) {
            $this->activePaths[$pathId]->terminate($lineNumber);
        }
    }

    public function getVariableAtLine(string $name, int $lineNumber): array
    {
        $possibleStates = [];

        // First, find paths that are actually active at this line
        $activePaths = [];
        foreach ($this->activePaths as $pathId => $path) {
            if ($path->isActiveAtLine($lineNumber)) {
                $activePaths[$pathId] = $path;
            }
        }

        // Separate specific paths (with line ranges) from general paths (main path)
        $specificPaths = [];
        $generalPaths = [];

        foreach ($activePaths as $pathId => $path) {
            if ($path->startLine !== null) {
                $specificPaths[$pathId] = $path;
            } else {
                $generalPaths[$pathId] = $path;
            }
        }

        // If we have specific paths active at this line, use them
        // Otherwise, fall back to general paths (main path)
        $pathsToUse = !empty($specificPaths) ? $specificPaths : $generalPaths;

        foreach ($pathsToUse as $pathId => $path) {
            // Get the latest assignment on this path before or at the line
            $variable = $path->getLatestVariableBeforeLine($name, $lineNumber);
            if ($variable) {
                $possibleStates[] = $variable;
            }
        }

        return $possibleStates;
    }

    protected function filterRelevantStates(array $states, int $lineNumber): array
    {
        if (empty($states)) {
            return $states;
        }

        // Group states by path hierarchy to understand which ones are mutually exclusive
        $pathGroups = [];
        foreach ($states as $state) {
            $pathParts = explode('-', $state->pathId);
            $basePathDepth = count($pathParts);
            $pathGroups[$basePathDepth][] = $state;
        }

        // For now, return all states, but this could be enhanced to understand
        // which paths are actually possible at a given line
        return $states;
    }

    public function getPossibleTypesAtLine(string $name, int $lineNumber): array
    {
        $states = $this->getVariableAtLine($name, $lineNumber);
        return array_map(fn($state) => $state->type, $states);
    }

    public function getUnionTypeAtLine(string $name, int $lineNumber): ?Type
    {
        $types = $this->getPossibleTypesAtLine($name, $lineNumber);

        if (empty($types)) {
            return null;
        }

        if (count($types) === 1) {
            return $types[0];
        }

        return TypeFactory::union(...$types);
    }

    public function getAllVariables(): array
    {
        $allVariables = [];

        // Collect unique variables from all paths
        $variableNames = [];

        foreach ($this->activePaths as $path) {
            foreach ($path->getAllVariables() as $name => $variable) {
                if (!in_array($name, $variableNames)) {
                    $variableNames[] = $name;
                }
            }
        }

        // For each variable, create a representative Variable object
        foreach ($variableNames as $name) {
            // Find the first occurrence
            $firstState = null;
            foreach ($this->activePaths as $path) {
                $variable = $path->getVariable($name);
                if ($variable && (!$firstState || $variable->lineNumber < $firstState->lineNumber)) {
                    $firstState = $variable;
                }
            }

            if ($firstState) {
                $allVariables[] = new Variable(
                    $firstState->name,
                    $firstState->type,
                    $firstState->lineNumber,
                    'method'
                );
            }
        }

        return $allVariables;
    }

    public function getActivePaths(): array
    {
        return $this->activePaths;
    }

    public function describeVariableAtLine(string $name, int $lineNumber): string
    {
        $states = $this->getVariableAtLine($name, $lineNumber);

        if (empty($states)) {
            return "Variable \${$name} is not defined or accessible at line {$lineNumber}";
        }

        if (count($states) === 1) {
            $state = $states[0];
            return "Variable \${$name} at line {$lineNumber}: {$state->type} (from path {$state->pathId} at line {$state->lineNumber})";
        }

        $types = array_map(fn($state) => (string)$state->type, $states);
        $uniqueTypes = array_unique($types);

        return "Variable \${$name} at line {$lineNumber}: " . implode(' | ', $uniqueTypes) . " (from " . count($states) . " possible paths)";
    }
}
