<?php

namespace Laravel\StaticAnalyzer\Result;

use Laravel\StaticAnalyzer\Types\ArrayType;
use Laravel\StaticAnalyzer\Types\Contracts\Type;
use Laravel\StaticAnalyzer\Types\UnionType;

class StateTrackerItem
{
    protected array $variables = [];

    protected array $snapshots = [];

    protected array $activeSnapshots = [];

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

    public function updateArrayKey(string $name, string $key, Type $type, int $lineNumber): void
    {
        $this->variables[$name] ??= [];

        $lastValue = $this->variables[$name][count($this->variables[$name]) - 1] ?? null;

        if ($lastValue === null) {
            dd('last value is null??', $name, $key, $type, $lineNumber);
        }

        if ($lastValue['type'] instanceof ArrayType) {
            $newType = new ArrayType(array_merge($lastValue['type']->value, [$key => $type]));
        } elseif ($lastValue['type'] instanceof UnionType) {
            $existingTypes = $lastValue['type']->types;
            $newType = new UnionType(
                array_map(fn ($t) => new ArrayType(array_merge($t->value, [$key => $type])), $existingTypes)
            );
        } else {
            dd('last value is not an array or union type??', $lastValue);
        }

        $changed = [
            'type' => $newType,
            'lineNumber' => $lineNumber,
        ];

        $this->variables[$name][] = $changed;

        if (count($this->activeSnapshots) > 0) {
            $activeSnapshot = $this->activeSnapshots[count($this->activeSnapshots) - 1];
            $this->snapshots[$activeSnapshot][$name] ??= [];
            $this->snapshots[$activeSnapshot][$name][] = $changed;
        }
    }

    public function getAtLine(string $name, int $lineNumber): array
    {
        $lines = array_filter($this->variables[$name], fn ($variable) => $variable['lineNumber'] <= $lineNumber);

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
}
