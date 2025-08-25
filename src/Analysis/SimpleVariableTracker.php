<?php

namespace Laravel\StaticAnalyzer\Analysis;

class SimpleVariableTracker
{
    /** @var Assignment[] */
    protected array $assignments = [];

    /** @var array<string, array> */
    protected array $pathConditionsStack = [];

    public function addAssignment(string $target, mixed $value, int $line, array $pathConditions = [], ?string $valueType = null): void
    {
        $this->assignments[] = new Assignment($target, $value, $line, $pathConditions, $valueType);
    }

    public function getPossibleValuesAt(string $target, int $line): array
    {
        $reachingAssignments = $this->getReachingAssignments($target, $line);

        if (empty($reachingAssignments)) {
            return [];
        }

        // Group assignments by their path conditions to identify possible execution paths
        $pathGroups = $this->groupAssignmentsByPath($reachingAssignments);

        return $this->resolvePossibleValues($pathGroups, $line);
    }

    public function getReachingAssignments(string $target, int $line): array
    {
        return array_filter($this->assignments, function (Assignment $assignment) use ($target, $line) {
            return $this->targetsMatch($assignment->target, $target) && $assignment->line <= $line;
        });
    }

    protected function targetsMatch(string $assignmentTarget, string $queryTarget): bool
    {
        // Exact match
        if ($assignmentTarget === $queryTarget) {
            return true;
        }

        // Handle array patterns like $arr[] affecting $arr
        if (
            str_ends_with($assignmentTarget, '[]') &&
            str_starts_with($queryTarget, rtrim($assignmentTarget, '[]'))
        ) {
            return true;
        }

        // Handle nested property/array access
        // e.g., $obj->prop affects $obj
        if (
            str_starts_with($assignmentTarget, $queryTarget) &&
            strlen($assignmentTarget) > strlen($queryTarget)
        ) {
            $remainder = substr($assignmentTarget, strlen($queryTarget));
            return str_starts_with($remainder, '[') || str_starts_with($remainder, '->');
        }

        return false;
    }

    protected function groupAssignmentsByPath(array $assignments): array
    {
        $groups = [];

        foreach ($assignments as $assignment) {
            $pathKey = $this->getPathKey($assignment->pathConditions);
            if (!isset($groups[$pathKey])) {
                $groups[$pathKey] = [];
            }
            $groups[$pathKey][] = $assignment;
        }

        return $groups;
    }

    protected function getPathKey(array $pathConditions): string
    {
        if (empty($pathConditions)) {
            return 'unconditional';
        }

        ksort($pathConditions);
        return md5(serialize($pathConditions));
    }

    protected function resolvePossibleValues(array $pathGroups, int $line): array
    {
        $possibleValues = [];

        foreach ($pathGroups as $pathKey => $assignments) {
            // Get the most recent assignment in this path
            $latestAssignment = $this->getLatestAssignment($assignments, $line);
            if ($latestAssignment) {
                $possibleValues[] = $latestAssignment->value;
            }
        }

        return array_unique($possibleValues, SORT_REGULAR);
    }

    protected function getLatestAssignment(array $assignments, int $line): ?Assignment
    {
        $validAssignments = array_filter($assignments, fn($a) => $a->line <= $line);

        if (empty($validAssignments)) {
            return null;
        }

        // Sort by line number descending to get the most recent
        usort($validAssignments, fn($a, $b) => $b->line <=> $a->line);

        return $validAssignments[0];
    }

    public function getAssignmentsSummary(string $target): array
    {
        $targetAssignments = array_filter(
            $this->assignments,
            fn($a) => $this->targetsMatch($a->target, $target)
        );

        return array_map(fn($a) => (string) $a, $targetAssignments);
    }

    public function getAllTargets(): array
    {
        $targets = array_map(fn($a) => $a->target, $this->assignments);
        return array_unique($targets);
    }

    public function getValuesByLine(string $target): array
    {
        $targetAssignments = array_filter(
            $this->assignments,
            fn($a) => $this->targetsMatch($a->target, $target)
        );

        $valuesByLine = [];
        foreach ($targetAssignments as $assignment) {
            $valuesByLine[$assignment->line] = $assignment->value;
        }

        return $valuesByLine;
    }

    public function debugPossibleValuesAt(string $target, int $line): array
    {
        $reachingAssignments = $this->getReachingAssignments($target, $line);
        $pathGroups = $this->groupAssignmentsByPath($reachingAssignments);

        $debug = [
            'target' => $target,
            'line' => $line,
            'reaching_assignments' => count($reachingAssignments),
            'path_groups' => count($pathGroups),
            'possible_values' => $this->getPossibleValuesAt($target, $line),
            'assignments_detail' => []
        ];

        foreach ($reachingAssignments as $assignment) {
            $debug['assignments_detail'][] = [
                'value' => $assignment->value,
                'line' => $assignment->line,
                'conditions' => $assignment->getPathConditionsString()
            ];
        }

        return $debug;
    }

    public function getAllAssignments(): array
    {
        return $this->assignments;
    }
}
